<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Teacher;
use App\Models\AuditLog;
use App\Models\Schedule;
use App\Models\Ufr;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use App\Services\NotificationService;

class AttendanceController extends Controller {

    const LATE_THRESHOLD_MINUTES = 15;
    const CHECK_IN_EARLY_MINUTES = 15;

    public function myPin(): JsonResponse
    {
        try {
            $user = auth()->user();
            $teacher = null;

            if ($user->hasRole('teacher')) {
                $teacher = Teacher::where('user_id', $user->id)->first();
                if (!$teacher) {
                    // Don't auto-create teacher - they should be properly created with UFR assignment
                    $ufr = Ufr::first();
                    if (!$ufr) {
                        return response()->json([
                            'message' => 'Aucune UFR disponible. Contactez l\'administration.',
                            'configured' => false,
                            'pin' => null,
                            'temporary' => false,
                        ], 400);
                    }
                    $teacher = Teacher::create([
                        'user_id' => $user->id,
                        'ufr_id' => $ufr->id,
                        'employee_id' => 'ENS-' . str_pad($user->id, 5, '0', STR_PAD_LEFT),
                        'hire_date' => now(),
                        'status' => 'active',
                        'volume_hours' => 0,
                        'hourly_rate' => 0,
                    ]);
                }
            } else {
                $teacher = Teacher::where('user_id', $user->id)->first();
            }

            // Retourner le PIN temporaire s'il existe
            $tempPin = $teacher ? cache()->get("teacher_temp_pin_{$teacher->id}") : null;

            // Déterminer le PIN permanent lisible (non hashé)
            $pin = $tempPin;
            if (!$pin && $teacher?->pin_code
                && !str_starts_with($teacher->pin_code, '$2y$')
                && !str_starts_with($teacher->pin_code, '$2b$')) {
                $pin = $teacher->pin_code;
            }
            if (!$pin && $user->attendance_pin
                && !str_starts_with($user->attendance_pin, '$2y$')
                && !str_starts_with($user->attendance_pin, '$2b$')) {
                $pin = $user->attendance_pin;
                if ($teacher && !$teacher->pin_code) {
                    try {
                        $teacher->update(['pin_code' => $pin]);
                    } catch (\Throwable $e) {
                        Log::warning('myPin: impossible de synchroniser pin_code sur teacher', [
                            'teacher_id' => $teacher->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Si le PIN est manquant ou crypté, attribuer un PIN clair par défaut
            if (!$pin && $teacher) {
                $pin = str_pad(1000 + ($user->id % 9000), 4, '0', STR_PAD_LEFT);
                try {
                    $teacher->update(['pin_code' => $pin]);
                } catch (\Throwable $e) {
                    Log::warning('myPin: impossible de sauvegarder pin_code', [
                        'teacher_id' => $teacher->id,
                        'error' => $e->getMessage(),
                    ]);
                }
                try {
                    $user->update(['attendance_pin' => $pin]);
                } catch (\Throwable $e) {
                    Log::warning('myPin: impossible de sauvegarder attendance_pin', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'configured' => (bool) ($pin || $user->attendance_pin || $teacher?->pin_code),
                'pin' => $pin,
                'temporary' => !is_null($tempPin),
            ]);
        } catch (\Throwable $e) {
            Log::error('myPin error: ' . $e->getMessage(), [
                'url' => request()->fullUrl(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Erreur lors de la récupération du PIN. Veuillez réessayer.',
                'configured' => false,
                'pin' => null,
                'temporary' => false,
            ], 500);
        }
    }

    public function generateTemporaryPin(): JsonResponse
    {
        $user = auth()->user();
        $teacher = Teacher::where('user_id', $user->id)->first();

        if (!$user->hasRole('teacher')) {
            return response()->json(['message' => 'Non-enseignant'], 403);
        }

        // Générer un PIN temporaire (valide 5 minutes)
        $tempPin = str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        cache()->put("teacher_temp_pin_{$teacher->id}", $tempPin, now()->addMinutes(5));

        return response()->json([
            'pin' => $tempPin,
            'expires_in_seconds' => 300,
            'message' => 'PIN temporaire généré (valide 5 minutes)'
        ]);
    }

    private function visibleUfrIds(User $user): array
    {
        if ($user->hasRole('admin')) {
            return [];
        }
        $ids = Ufr::where('head_id', $user->id)->pluck('id');
        $teacherUfrId = Teacher::where('user_id', $user->id)->value('ufr_id');
        if ($teacherUfrId) {
            $ids->push($teacherUfrId);
        }
        return $ids->unique()->values()->all();
    }

    private function canAccessTeacher(Teacher $teacher, User $user): bool
    {
        return $user->hasRole('admin') || in_array($teacher->ufr_id, $this->visibleUfrIds($user));
    }

    private function restrictToVisibleUfr($query, User $user): void
    {
        if ($user->hasRole('admin')) {
            return;
        }

        $ufrIds = $this->visibleUfrIds($user);
        $query->where(function ($builder) use ($ufrIds) {
            $builder->whereHas('teacher', fn($teacher) => $teacher->whereIn('ufr_id', $ufrIds))
                ->orWhere(function ($legacy) use ($ufrIds) {
                    $legacy->whereNull('teacher_id')
                        ->whereHas('user.teacher', fn($teacher) => $teacher->whereIn('ufr_id', $ufrIds));
                });
        });
    }

    public function getActiveCode(Request $request): JsonResponse {
        return response()->json([
            'code' => null,
            'is_open' => true,
            'expires_at' => null,
            'remaining_seconds' => null,
            'message' => 'Système de PIN individuel — plus de code temporaire',
        ]);
    }

    public function generateCode(Request $request): JsonResponse {
        return response()->json([
            'message' => 'Système de codes temporaires désactivé. Les enseignants utilisent désormais leur PIN personnel.',
            'code' => null,
            'expires_at' => null,
        ]);
    }

    public function checkIn(Request $request): JsonResponse {
        $user = auth()->user();
        $teacher = null;

        if (!$user->hasRole('teacher')) {
            $attendance = Attendance::where('user_id', $user->id)
                ->whereDate('attendance_date', Carbon::today())
                ->first();
            if ($attendance?->check_in_time) {
                return response()->json(['message' => 'Vous avez déjà pointé aujourd’hui'], 422);
            }

            $attendance = Attendance::updateOrCreate(
                ['user_id' => $user->id, 'attendance_date' => Carbon::today()],
                ['teacher_id' => null, 'check_in_time' => now()->toTimeString(), 'status' => 'present']
            );

            return response()->json(['message' => 'Pointage d’entrée enregistré', 'attendance' => $attendance]);
        }

        $teacher = Teacher::where('user_id', $user->id)->first();
        if (!$teacher) {
            $ufr = Ufr::first();
            if (!$ufr) {
                return response()->json(['message' => 'Aucune UFR disponible. Contactez l\'administration.'], 400);
            }
            $teacher = Teacher::create([
                'user_id' => $user->id,
                'ufr_id' => $ufr->id,
                'employee_id' => 'ENS-' . str_pad($user->id, 5, '0', STR_PAD_LEFT),
                'hire_date' => now(),
                'status' => 'active',
                'volume_hours' => 0,
                'hourly_rate' => 0,
            ]);
        }

        $validated = $request->validate([
            'pin' => 'required|string|min:4|max:6',
        ]);

        $pinInput = (string) $validated['pin'];
        $tempPin = cache()->get("teacher_temp_pin_{$teacher->id}");

        $pinValid = false;
        if ($tempPin && $pinInput === (string) $tempPin) {
            $pinValid = true;
        } elseif ($teacher->pin_code) {
            if ($pinInput === (string) $teacher->pin_code || Hash::check($pinInput, $teacher->pin_code)) {
                $pinValid = true;
            }
        }
        if (!$pinValid && $user->attendance_pin) {
            if ($pinInput === (string) $user->attendance_pin || Hash::check($pinInput, $user->attendance_pin)) {
                $pinValid = true;
            }
        }

        if (!$pinValid) {
            return response()->json(['message' => 'Code PIN invalide'], 422);
        }

        $now = now();
        $today = Carbon::today();

        $schedule = $this->getCurrentScheduleForTeacher($teacher, $now);

        if (!$schedule) {
            $exists = Attendance::where('user_id', $user->id)
                ->where('attendance_date', $today)
                ->whereNull('schedule_id')
                ->whereNotNull('check_in_time')
                ->whereNull('check_out_time')
                ->first();

            if ($exists) {
                return response()->json(['message' => 'Vous avez déjà pointé votre arrivée aujourd’hui'], 422);
            }

            $attendance = Attendance::create([
                'user_id' => $user->id,
                'teacher_id' => $teacher->id,
                'schedule_id' => null,
                'attendance_date' => $today,
                'check_in_time' => $now->toTimeString(),
                'status' => 'present',
                'notes' => 'Pointage présence générale enseignant',
            ]);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'check_in',
                'model' => 'Attendance',
                'model_id' => $attendance->id,
                'new_values' => [
                    'status' => 'present',
                    'check_in_time' => $now->format('H:i:s'),
                    'schedule_id' => null,
                    'via_pin' => true,
                ],
                'ip_address' => $request->ip(),
            ]);

            $message = 'Pointage arrivée enregistré à ' . $now->format('H:i') . ' (Présence générale)';
            NotificationService::send(
                $user->id,
                'Pointage enregistré',
                $message,
                'success',
                '/attendance'
            );

            return response()->json([
                'message' => $message,
                'attendance' => $attendance,
                'status' => 'present',
                'schedule' => null,
            ]);
        }

        $scheduleStart = Carbon::parse($schedule->start_time);
        $windowStart = $scheduleStart->copy()->subMinutes(self::CHECK_IN_EARLY_MINUTES);

        if ($now->lt($windowStart)) {
            $exists = Attendance::where('user_id', $user->id)
                ->where('attendance_date', $today)
                ->whereNotNull('check_in_time')
                ->whereNull('check_out_time')
                ->first();

            if ($exists) {
                return response()->json([
                    'message' => 'Pointage d’arrivée déjà actif. Le cours commence à ' . $scheduleStart->format('H:i'),
                ], 422);
            }

            $attendance = Attendance::create([
                'user_id' => $user->id,
                'teacher_id' => $teacher->id,
                'schedule_id' => null,
                'attendance_date' => $today,
                'check_in_time' => $now->toTimeString(),
                'status' => 'present',
                'notes' => 'Arrivée anticipée avant cours (' . ($schedule->subject?->name ?? 'cours') . ')',
            ]);

            return response()->json([
                'message' => 'Pointage d’arrivée enregistré à ' . $now->format('H:i') . ' (Arrivée anticipée)',
                'attendance' => $attendance,
                'status' => 'present',
                'schedule' => null,
            ]);
        }

        return DB::transaction(function () use ($teacher, $today, $now, $request, $user, $schedule) {
            $exists = Attendance::where('user_id', $user->id)
                ->where('attendance_date', $today)
                ->where('schedule_id', $schedule->id)
                ->first();

            if ($exists && $exists->check_in_time) {
                return response()->json([
                    'message' => 'Vous avez déjà pointé pour ce cours (' . $schedule->subject->name . ')',
                ], 422);
            }

            $status = 'present';
            $notes = null;

            $scheduleStart = Carbon::parse($schedule->start_time);
            $lateThreshold = $scheduleStart->copy()->addMinutes(self::LATE_THRESHOLD_MINUTES);
            if ($now->gt($lateThreshold)) {
                $status = 'late';
                $notes = 'Retard auto — cours prévu à ' . $schedule->start_time . ', arrivée à ' . $now->format('H:i');
            }

            $attendance = Attendance::create([
                'user_id' => $user->id,
                'teacher_id' => $teacher->id,
                'schedule_id' => $schedule->id,
                'attendance_date' => $today,
                'check_in_time' => $now->toTimeString(),
                'status' => $status,
                'notes' => $notes,
            ]);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'check_in',
                'model' => 'Attendance',
                'model_id' => $attendance->id,
                'new_values' => [
                    'status' => $status,
                    'check_in_time' => $now->format('H:i:s'),
                    'schedule_id' => $schedule->id,
                    'subject' => $schedule->subject?->name,
                    'via_pin' => true,
                ],
                'ip_address' => $request->ip(),
            ]);

            $message = 'Pointage arrivée enregistré à ' . $now->format('H:i') . ' — ' . $schedule->subject?->name;
            if ($status === 'late') {
                $message = 'Retard enregistré — arrivée à ' . $now->format('H:i');
            }

            NotificationService::send(
                $user->id,
                $status === 'late' ? 'Retard enregistré' : 'Pointage enregistré',
                $message,
                $status === 'late' ? 'warning' : 'success',
                '/attendance'
            );

            $attendance->load('schedule.subject', 'schedule.classroom');

            return response()->json([
                'message' => $message,
                'attendance' => $attendance,
                'status' => $status,
                'schedule' => $schedule->load('subject', 'classroom'),
            ]);
        });
    }

    public function checkOut(Request $request): JsonResponse {
        $user = auth()->user();
        $teacher = Teacher::where('user_id', $user->id)->first();

        $attendance = Attendance::where('user_id', $user->id)
            ->where('attendance_date', Carbon::today())
            ->whereNotNull('check_in_time')
            ->whereNull('check_out_time')
            ->latest('check_in_time')
            ->first();

        if (!$attendance) {
            return response()->json(['message' => 'Aucun pointage d\'entrée actif trouvé'], 422);
        }

        $checkOutTime = now();
        $attendanceDate = $attendance->attendance_date instanceof Carbon
            ? $attendance->attendance_date->format('Y-m-d')
            : $attendance->attendance_date;
        $inTime = Carbon::parse($attendanceDate . ' ' . $attendance->check_in_time);
        $checkOutParsed = Carbon::parse($attendanceDate . ' ' . $checkOutTime->toTimeString());

        if ($checkOutParsed->lt($inTime)) {
            $checkOutParsed->addDay();
        }

        $hoursWorked = round($checkOutParsed->diffInMinutes($inTime) / 60, 2);

        $attendance->update([
            'check_out_time' => $checkOutTime->toTimeString(),
            'hours_worked' => $hoursWorked,
        ]);

        if ($teacher && $teacher->volume_hours !== null) {
            $consumed = (float) \App\Models\Attendance::where('teacher_id', $teacher->id)
                ->whereYear('attendance_date', now()->year)
                ->whereNotNull('hours_worked')
                ->sum('hours_worked');
            $remaining = max(0, $teacher->volume_hours - $consumed);

            if ($remaining <= 0) {
                NotificationService::send(
                    $user->id,
                    'Volume horaire épuisé',
                    'Votre volume d\'heures contractuel est maintenant épuisé. Contactez les RH.',
                    'danger',
                    '/attendance'
                );
            } elseif ($remaining <= 5) {
                NotificationService::send(
                    $user->id,
                    'Volume horaire bientôt épuisé',
                    'Il vous reste ' . round($remaining, 1) . 'h sur votre volume contractuel.',
                    'warning',
                    '/attendance'
                );
            }
        }

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'check_out',
            'model' => 'Attendance',
            'model_id' => $attendance->id,
            'new_values' => ['check_out_time' => $checkOutTime->format('H:i:s'), 'hours_worked' => $hoursWorked],
            'ip_address' => $request->ip(),
        ]);

        $message = 'Pointage sortie enregistré à ' . $checkOutTime->format('H:i') . ' — ' . $hoursWorked . 'h travaillées';
        if ($teacher && $teacher->volume_hours !== null) {
            $message .= ' (volume restant : ' . round($remaining, 1) . 'h)';
        }

        return response()->json([
            'message' => $message,
            'attendance' => $attendance,
        ]);
    }

    public function reportAbsence(Request $request): JsonResponse {
        $user = auth()->user();
        $teacher = Teacher::where('user_id', $user->id)->first();

        if (!$user->hasRole('teacher')) {
            if (!$user->hasAnyRole(['admin', 'hr_manager', 'secretary', 'accountant', 'academic_manager'])) {
                return response()->json(['message' => 'Signalement non autorisé pour ce profil'], 403);
            }

            $validated = $request->validate([
                'attendance_date' => 'required|date|before_or_equal:today',
                'status' => 'required|in:absent,late',
                'reason' => 'nullable|string|max:500',
            ]);

            $attendance = Attendance::updateOrCreate(
                ['user_id' => $user->id, 'attendance_date' => $validated['attendance_date']],
                ['teacher_id' => null, 'status' => $validated['status'], 'notes' => $validated['reason'] ?? null]
            );

            return response()->json(['message' => 'Signalement enregistré', 'attendance' => $attendance]);
        }

        $validated = $request->validate([
            'attendance_date' => 'required|date|before_or_equal:today',
            'status' => 'required|in:absent,late',
            'reason' => 'nullable|string|max:500',
        ]);

        $existing = Attendance::where('user_id', $user->id)
            ->where('attendance_date', $validated['attendance_date'])
            ->first();

        if ($existing && $existing->status === 'present' && $existing->check_in_time) {
            return response()->json(['message' => 'Vous êtes déjà marqué présent ce jour. Contactez les RH.'], 422);
        }

        if ($existing) {
            $existing->update([
                'status' => $validated['status'],
                'notes' => !empty($validated['reason']) ? $validated['reason'] : $existing->notes,
                'is_justified' => false,
                'justification_status' => null,
                'justified_by' => null,
                'justified_at' => null,
                'justification_notes' => null,
            ]);
            $attendance = $existing;
        } else {
            $attendance = Attendance::create([
                'user_id' => $user->id,
                'teacher_id' => $teacher->id,
                'attendance_date' => $validated['attendance_date'],
                'status' => $validated['status'],
                'notes' => $validated['reason'] ?? null,
            ]);
        }

        $statusLabel = $validated['status'] === 'late' ? 'un retard' : 'une absence';
        $dateLabel = \Carbon\Carbon::parse($validated['attendance_date'])->translatedFormat('d/m/Y');

        NotificationService::send(
            $user->id,
            'Signalement enregistré',
            'Votre ' . $statusLabel . ' du ' . $dateLabel . ' a été signalé et est en attente de validation.',
            'info',
            '/attendance'
        );

        $adminUsers = \App\Models\User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['admin', 'hr_manager', 'secretary']);
        })->pluck('id');

        foreach ($adminUsers as $adminId) {
            NotificationService::send(
                $adminId,
                'Nouveau signalement ' . ($validated['status'] === 'late' ? 'de retard' : "d'absence"),
                "L'enseignant " . $teacher->user->name . " a signalé " . $statusLabel . " pour le " . $dateLabel . ($validated['reason'] ? " — " . $validated['reason'] : ''),
                'warning',
                '/attendance'
            );
        }

        return response()->json([
            'message' => 'Signalement enregistré',
            'attendance' => $attendance,
        ]);
    }

    public function declareAvailability(Request $request): JsonResponse {
        $user = auth()->user();
        $teacher = Teacher::where('user_id', $user->id)->first();

        if (!$user->hasRole('teacher')) {
            return response()->json(['message' => 'Seuls les enseignants peuvent déclarer leur disponibilité'], 403);
        }

        $validated = $request->validate([
            'date' => 'required|date|before_or_equal:today',
            'available' => 'required|boolean',
            'time_slots' => 'nullable|array',
        ]);

        $existing = Attendance::where('user_id', $user->id)
            ->where('attendance_date', $validated['date'])
            ->first();

        if ($existing && $existing->check_in_time) {
            return response()->json(['message' => 'Impossible de modifier: un pointage d\'arrivée existe déjà'], 409);
        }

        Attendance::updateOrCreate(
            [
                'user_id' => $user->id,
                'attendance_date' => $validated['date'],
            ],
            [
                'user_id' => $user->id,
                'teacher_id' => $teacher->id,
                'status' => $validated['available'] ? 'present' : 'absent',
                'notes' => $validated['time_slots'] ? json_encode($validated['time_slots']) : null,
                'is_justified' => false,
                'justification_status' => null,
                'justified_by' => null,
                'justified_at' => null,
                'justification_notes' => null,
            ]
        );

        return response()->json(['message' => 'Disponibilité enregistrée']);
    }

    public function recordAbsence(Request $request): JsonResponse {
        $validated = $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'attendance_date' => 'required|date|before_or_equal:today',
            'status' => 'required|in:absent,late,half_day',
            'reason' => 'nullable|string|max:500',
        ]);

        $teacher = Teacher::with('user')->find($validated['teacher_id']);
        $user = auth()->user();
        if (!$user->hasRole('admin') && $user->hasRole('academic_manager')
            && (!$teacher || !$this->canAccessTeacher($teacher, $user))) {
            return response()->json(['message' => 'Vous ne pouvez gérer que les enseignants de votre UFR'], 403);
        }

        $existing = Attendance::where('user_id', $teacher->user_id)
            ->where('attendance_date', $validated['attendance_date'])
            ->first();

        if ($existing && $existing->check_in_time && in_array($existing->status, ['present', 'late'])) {
            return response()->json(['message' => 'Impossible d\'enregistrer : un pointage d\'arrivée existe déjà pour cette date.'], 422);
        }

            $attendance = Attendance::updateOrCreate(
            [
                'user_id' => $teacher->user_id,
                'attendance_date' => $validated['attendance_date'],
            ],
            [
                'user_id' => $teacher->user_id,
                'teacher_id' => $teacher->id,
                'status' => $validated['status'],
                'notes' => $validated['reason'] ?? null,
                'is_justified' => false,
                'justification_status' => null,
                'justified_by' => null,
                'justified_at' => null,
                'justification_notes' => null,
            ]
        );

        if ($teacher && $teacher->user) {
            $statusLabel = match ($validated['status']) {
                'absent' => 'absence',
                'late' => 'retard',
                'half_day' => 'demi-journée d\'absence',
                default => $validated['status'],
            };
            NotificationService::send(
                $teacher->user->id,
                'Pointage enregistré',
                'Une ' . $statusLabel . ' a été enregistrée pour le ' . $validated['attendance_date'] . ($validated['reason'] ? ' — ' . $validated['reason'] : ''),
                'warning',
                '/attendance'
            );
        }

        return response()->json([
            'message' => 'Absence enregistrée',
            'attendance' => $attendance,
        ]);
    }

    public function validateAbsence(Request $request, Attendance $attendance): JsonResponse {
        $user = auth()->user();

        $attendance->loadMissing('teacher');
        if (!$user->hasRole('admin') && $user->hasRole('academic_manager')
            && (!$attendance->teacher || !$this->canAccessTeacher($attendance->teacher, $user))) {
            return response()->json(['message' => 'Vous ne pouvez traiter que les absences de votre UFR'], 403);
        }

        if (!in_array($attendance->status, ['absent', 'late', 'half_day'], true)) {
            return response()->json(['message' => 'Seuls les absences, retards ou demi-journées peuvent être traités'], 422);
        }

        if (in_array($attendance->justification_status, ['justified', 'unjustified'], true)) {
            $attendance->load(['teacher.user', 'user', 'justifiedBy']);

            return response()->json([
                'message' => 'Ce signalement a déjà été traité par ' . ($attendance->justifiedBy?->name ?? 'un responsable') . '.',
                'attendance' => $attendance,
            ], 409);
        }

        $validated = $request->validate([
            'justified' => 'required|boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($attendance, $validated, $user, $request) {
            $attendance->update([
                'is_justified' => $validated['justified'],
                'justification_status' => $validated['justified'] ? 'justified' : 'unjustified',
                'justified_by' => $user->id,
                'justified_at' => now(),
                'justification_notes' => $validated['notes'] ?? null,
            ]);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'validate_absence',
                'model' => 'Attendance',
                'model_id' => $attendance->id,
                'new_values' => [
                    'justification_status' => $attendance->justification_status,
                    'justification_notes' => $attendance->justification_notes,
                ],
                'ip_address' => $request->ip(),
            ]);
        });

        $teacher = Teacher::with('user')->find($attendance->teacher_id);
        $notifUserId = $teacher?->user?->id ?? $attendance->user_id;
        $decision = $validated['justified'] ? 'justifiée' : 'non justifiée';
        if ($notifUserId) {
            NotificationService::send(
                $notifUserId,
                'Signalement ' . $decision,
                'Votre ' . ($attendance->status === 'late' ? 'retard' : 'absence') . ' du ' . $attendance->attendance_date . ' a été traité(e) comme ' . $decision . ' par ' . $user->name . '.',
                $validated['justified'] ? 'success' : 'danger',
                '/attendance'
            );
        }

        $reviewers = \App\Models\User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['admin', 'hr_manager', 'secretary', 'academic_manager']);
        })->whereKeyNot($user->id)->get();
        foreach ($reviewers as $reviewer) {
            NotificationService::send(
                $reviewer->id,
                'Signalement traité',
                ($teacher?->user?->name ?? 'Un enseignant') . ' : ' . ($attendance->status === 'late' ? 'retard' : 'absence') . ' du ' . $attendance->attendance_date . ' traité par ' . $user->name . ' (' . $decision . ').',
                'info',
                '/attendance'
            );
        }

        $attendance->load(['teacher.user', 'user', 'justifiedBy']);

        return response()->json([
            'message' => $validated['justified'] ? 'Signalement justifié' : 'Signalement non justifié',
            'attendance' => $attendance,
        ]);
    }

    public function index(Request $request): JsonResponse {
        $user = auth()->user();
        $teacher = Teacher::where('user_id', $user->id)->first();

        if ($request->boolean('self')) {
            $history = Attendance::with('justifiedBy')->where('user_id', $user->id)
                ->orderBy('attendance_date', 'desc')->limit(28)->get();
            $today = Attendance::with('justifiedBy')
                ->where('user_id', $user->id)
                ->whereDate('attendance_date', Carbon::today())
                ->latest('id')
                ->first();
            return response()->json([
                'history' => $history,
                'today' => $today,
            ]);
        }

        // Si l'utilisateur est enseignant et ne demande pas explicitement la vue registre/gestion
        $isManagementRequest = $request->boolean('management') || $request->filled('ufr_id') || ($request->filled('date') && $user->hasAnyRole(['admin', 'hr_manager', 'secretary', 'accountant', 'academic_manager']));
        if ($user->hasRole('teacher') && !$isManagementRequest) {
            $teacher = Teacher::where('user_id', $user->id)->first();
            if (!$teacher) {
                $ufr = Ufr::first();
                if (!$ufr) {
                    return response()->json(['message' => 'Aucune UFR disponible. Contactez l\'administration.'], 400);
                }
                $teacher = Teacher::create([
                    'user_id' => $user->id,
                    'ufr_id' => $ufr->id,
                    'employee_id' => 'ENS-' . str_pad($user->id, 5, '0', STR_PAD_LEFT),
                    'hire_date' => now(),
                    'status' => 'active',
                    'volume_hours' => 0,
                    'hourly_rate' => 0,
                ]);
            }

            $week = $request->input('week');
            $query = Attendance::where('user_id', $user->id);

            if ($week) {
                $start = Carbon::parse($week)->startOfWeek();
                $end = $start->copy()->endOfWeek();
                $query->whereBetween('attendance_date', [$start, $end]);
            }

            $history = $query->with(['justifiedBy', 'schedule.subject', 'schedule.classroom'])->orderBy('attendance_date', 'desc')
                ->limit($week ? 7 : 28)
                ->get();

            $todayAttendances = Attendance::with(['justifiedBy', 'schedule.subject', 'schedule.classroom'])
                ->where('user_id', $user->id)
                ->where('attendance_date', Carbon::today())
                ->get();

            // Cours du jour avec leur statut de pointage
            $todaySchedules = $this->getTodaySchedulesForTeacher($teacher, Carbon::today())
                ->map(function ($schedule) use ($todayAttendances) {
                    $att = $todayAttendances->firstWhere('schedule_id', $schedule->id);
                    return [
                        'id' => $schedule->id,
                        'subject_name' => $schedule->subject?->name,
                        'classroom_name' => $schedule->classroom?->name,
                        'teaching_type' => $schedule->teachingType?->code,
                        'level' => $schedule->level,
                        'day_of_week' => $schedule->day_of_week,
                        'start_time' => $schedule->start_time,
                        'end_time' => $schedule->end_time,
                        'check_in_time' => $att?->check_in_time,
                        'check_out_time' => $att?->check_out_time,
                        'status' => $att?->status ?? null,
                        'attendance_id' => $att?->id ?? null,
                    ];
                });

            return response()->json([
                'history' => $history,
                'today' => $todayAttendances->sortByDesc('id')->first(),
                'today_schedules' => $todaySchedules,
            ]);
        }

        if (!$user->hasAnyRole(['admin', 'hr_manager', 'secretary', 'accountant', 'academic_manager'])) {
            $history = Attendance::with('justifiedBy')->where('user_id', $user->id)
                ->orderBy('attendance_date', 'desc')
                ->limit(28)
                ->get();

            $today = Attendance::with('justifiedBy')->where('user_id', $user->id)
                ->where('attendance_date', Carbon::today())
                ->first();

            return response()->json([
                'history' => $history,
                'today' => $today,
            ]);
        }

        $query = Attendance::with(['teacher.user.teacher.ufr', 'user', 'justifiedBy'])->orderBy('attendance_date', 'desc');

        if ($request->filled('date')) {
            $query->whereDate('attendance_date', $request->date);
        }

        if ($request->filled('ufr_id')) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('teacher', function ($q2) use ($request) {
                    $q2->where('ufr_id', $request->ufr_id);
                })->orWhere(function ($q2) use ($request) {
                    $q2->whereNull('teacher_id')
                       ->whereHas('user.teacher', function ($q3) use ($request) {
                           $q3->where('ufr_id', $request->ufr_id);
                       });
                });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('teacher.user', function ($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%");
                })->orWhereHas('user', function ($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%");
                });
            });
        }

        $this->restrictToVisibleUfr($query, $user);

        return response()->json($query->paginate($request->input('per_page', 25)));
    }

    public function stats(Request $request): JsonResponse {
        $user = auth()->user();
        $date = $request->input('date', Carbon::today()->toDateString());
        $period = $request->input('period', 'day');

        $query = Attendance::query();

        $this->restrictToVisibleUfr($query, $user);

        if ($period === 'day') {
            $query->where('attendance_date', $date);
        } elseif ($period === 'week') {
            $start = Carbon::parse($date)->startOfWeek();
            $end = $start->copy()->endOfWeek();
            $query->whereBetween('attendance_date', [$start, $end]);
        } elseif ($period === 'month') {
            $start = Carbon::parse($date)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $query->whereBetween('attendance_date', [$start, $end]);
        }

        $total = (clone $query)->count();
        $present = (clone $query)->where('status', 'present')->count();
        $late = (clone $query)->where('status', 'late')->count();
        $absent = (clone $query)->where('status', 'absent')->count();
        $justified = (clone $query)->where('is_justified', true)->count();
        $totalHours = (clone $query)->sum('hours_worked');

        $rate = $total > 0 ? round((($present + $late) / $total) * 100, 1) : 0;

        $trend = [];
        if ($period === 'month') {
            for ($i = 6; $i >= 0; $i--) {
                $day = Carbon::today()->subDays($i);
                $dayCount = Attendance::where('attendance_date', $day->toDateString())
                    ->where('status', 'present')
                    ->count();
                $trend[] = [
                    'day' => $day->translatedFormat('D'),
                    'count' => $dayCount,
                ];
            }
        }

        return response()->json([
            'total' => $total,
            'present' => $present,
            'late' => $late,
            'absent' => $absent,
            'justified' => $justified,
            'total_hours' => (float) $totalHours,
            'attendance_rate' => $rate,
            'period' => $period,
            'date' => $date,
            'trend' => $trend,
        ]);
    }

    public function weekly(Request $request): JsonResponse {
        $user = auth()->user();
        $teacher = Teacher::where('user_id', $user->id)->first();

        $weeksBack = (int) $request->input('weeks', 0);
        $startOfWeek = Carbon::now()->subWeeks($weeksBack)->startOfWeek();
        $endOfWeek = $startOfWeek->copy()->endOfWeek();

        $attendances = Attendance::where('user_id', $user->id)
            ->whereBetween('attendance_date', [$startOfWeek, $endOfWeek])
            ->get()
            ->keyBy(fn($a) => $a->attendance_date instanceof Carbon ? $a->attendance_date->format('Y-m-d') : $a->attendance_date);

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $startOfWeek->copy()->addDays($i);
            $key = $day->format('Y-m-d');
            $att = $attendances->get($key);

            $days[] = [
                'date' => $key,
                'day_name' => $day->translatedFormat('D'),
                'day_short' => $day->translatedFormat('D'),
                'day_number' => $day->day,
                'is_today' => $day->isToday(),
                'status' => $att?->status ?? 'none',
                'check_in' => $att?->check_in_time ? substr($att->check_in_time, 0, 5) : null,
                'check_out' => $att?->check_out_time ? substr($att->check_out_time, 0, 5) : null,
                'hours_worked' => $att?->hours_worked,
                'is_justified' => $att?->is_justified ?? false,
                'justification_status' => $att?->justification_status,
                'justification_notes' => $att?->justification_notes,
                'notes' => $att?->notes,
            ];
        }

        return response()->json([
            'week_start' => $startOfWeek->toDateString(),
            'week_end' => $endOfWeek->toDateString(),
            'days' => $days,
        ]);
    }

    public function export(Request $request) {
        $user = auth()->user();

        $query = Attendance::with('teacher.user', 'teacher.ufr', 'user');

        if ($request->filled('date')) {
            $query->where('attendance_date', $request->date);
        } elseif ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('attendance_date', $request->month)
                ->whereYear('attendance_date', $request->year);
        } else {
            $query->where('attendance_date', Carbon::today());
        }

        if ($request->filled('ufr_id')) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('teacher', function ($q2) use ($request) {
                    $q2->where('ufr_id', $request->ufr_id);
                })->orWhere(function ($q2) use ($request) {
                    $q2->whereNull('teacher_id')
                       ->whereHas('user.teacher', function ($q3) use ($request) {
                           $q3->where('ufr_id', $request->ufr_id);
                       });
                });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $this->restrictToVisibleUfr($query, $user);

        $attendances = $query->with('justifiedBy')->orderBy('attendance_date', 'desc')->get();

        $filename = "pointage_export_" . now()->format('Y-m-d_H-i-s') . ".csv";
        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0",
        ];

        $columns = ['Enseignant', 'Matricule', 'Département', 'Date', 'Entrée', 'Sortie', 'Durée (h)', 'Statut', 'Justification', 'Motif signalé', 'Note validation', 'Validé par', 'Date validation'];

        $callback = function () use ($attendances, $columns) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");
            fputcsv($file, $columns);

            foreach ($attendances as $a) {
                fputcsv($file, [
                    optional(optional($a->teacher)->user)->name ?? $a->user->name ?? 'Inconnu',
                    optional($a->teacher)->employee_id ?? '-',
                    optional(optional($a->teacher)->ufr)->name ?? '-',
                    $a->attendance_date?->format('d/m/Y') ?? '-',
                    $a->check_in_time ? substr($a->check_in_time, 0, 5) : '-',
                    $a->check_out_time ? substr($a->check_out_time, 0, 5) : '-',
                    $a->hours_worked ?? '-',
                    match ($a->status) {
                        'present' => 'Présent',
                        'absent' => 'Absent',
                        'late' => 'Retard',
                        'half_day' => 'Demi-journée',
                        default => $a->status,
                    },
                    $a->justification_status === 'justified' ? 'Oui' : ($a->justification_status === 'unjustified' ? 'Non' : 'En attente'),
                    $a->notes ?? '-',
                    $a->justification_notes ?? '-',
                    $a->validator?->name ?? '-',
                    $a->justified_at?->format('d/m/Y H:i') ?? '-',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function update(Request $request, Attendance $attendance): JsonResponse {
        $validated = $request->validate([
            'status' => 'sometimes|in:present,absent,late,half_day',
            'notes' => 'nullable|string',
            'check_in_time' => 'sometimes|date_format:H:i:s',
            'check_out_time' => 'sometimes|date_format:H:i:s|after:check_in_time',
        ]);

        if (isset($validated['check_in_time']) || isset($validated['check_out_time'])) {
            $inTime = $validated['check_in_time'] ?? $attendance->check_in_time;
            $outTime = $validated['check_out_time'] ?? $attendance->check_out_time;
            if ($inTime && $outTime) {
                $in = Carbon::createFromFormat('H:i:s', $inTime);
                $out = Carbon::createFromFormat('H:i:s', $outTime);
                $validated['hours_worked'] = round($out->diffInMinutes($in) / 60, 2);
            }
        }

        if (array_key_exists('status', $validated) || array_key_exists('notes', $validated)) {
            $validated['is_justified'] = false;
            $validated['justification_status'] = null;
            $validated['justified_by'] = null;
            $validated['justified_at'] = null;
            $validated['justification_notes'] = null;
        }

        $attendance->update($validated);

        return response()->json(['message' => 'Pointage mis à jour', 'attendance' => $attendance->fresh()]);
    }

    public function report(Request $request): JsonResponse {
        $user = auth()->user();
        $query = Attendance::with('teacher.user', 'teacher.ufr', 'user')
            ->selectRaw("teacher_id, user_id, COUNT(*) as total, SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as presents, SUM(CASE WHEN status IN ('absent','late') THEN 1 ELSE 0 END) as absences")
            ->groupBy('teacher_id', 'user_id');

        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('attendance_date', $request->month)
                ->whereYear('attendance_date', $request->year);
        }

        if ($request->filled('ufr_id')) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('teacher', function ($q2) use ($request) {
                    $q2->where('ufr_id', $request->ufr_id);
                })->orWhere(function ($q2) use ($request) {
                    $q2->whereNull('teacher_id')
                       ->whereHas('user.teacher', function ($q3) use ($request) {
                           $q3->where('ufr_id', $request->ufr_id);
                       });
                });
            });
        }

        $this->restrictToVisibleUfr($query, $user);

        return response()->json($query->get());
    }

    public function getAvailabilities(Request $request): JsonResponse {
        $user = auth()->user();
        if (!$user->hasAnyRole(['admin', 'hr_manager', 'secretary', 'academic_manager'])) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $attendances = Attendance::whereHas('teacher')
            ->with('teacher.user')
            ->where('notes', 'LIKE', '[%')
            ->get()
            ->filter(function ($attendance) {
                $decoded = json_decode($attendance->notes, true);
                return is_array($decoded);
            });

        if (!$user->hasRole('admin') && $user->hasRole('academic_manager')) {
            $ufrIds = $this->visibleUfrIds($user);
            $attendances = $attendances->filter(fn($a) => in_array($a->teacher?->ufr_id, $ufrIds));
        }

        if ($request->filled('date')) {
            $attendances = $attendances->where('attendance_date', $request->date);
        }

        return response()->json($attendances->values());
    }

    private function getDayName(Carbon $date): string
    {
        return ['', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'][$date->dayOfWeekIso];
    }

    private function getCurrentScheduleForTeacher(Teacher $teacher, Carbon $now): ?Schedule
    {
        $dayName = $this->getDayName($now);
        $nowTime = $now->format('H:i');

        $schedules = Schedule::where('teacher_id', $teacher->id)
            ->where('day_of_week', $dayName)
            ->where('is_archived', false)
            ->orderBy('start_time')
            ->get();

        if ($schedules->isEmpty()) {
            return null;
        }

        // Cours en cours : start_time <= now <= end_time
        foreach ($schedules as $s) {
            $start = substr((string) $s->start_time, 0, 5);
            $end = substr((string) $s->end_time, 0, 5);
            if ($nowTime >= $start && $nowTime <= $end) {
                return $s;
            }
        }

        // Prochain cours à venir (le plus proche)
        foreach ($schedules as $s) {
            $start = substr((string) $s->start_time, 0, 5);
            if ($start > $nowTime) {
                return $s;
            }
        }

        return null;
    }

    public function checkInOnline(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $teacher = Teacher::where('user_id', $user->id)->first();

            // Vérifier que c'est un enseignant
            if (!$user->hasRole('teacher')) {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            if (!$teacher) {
                return response()->json(['message' => 'Enseignant non trouvé'], 404);
            }

            $validated = $request->validate([
                'schedule_id' => 'required|exists:schedules,id',
            ]);

            $schedule = Schedule::with('subject')->findOrFail($validated['schedule_id']);

            // Vérifier que le cours appartient à cet enseignant
            if ($schedule->teacher_id !== $teacher->id) {
                return response()->json(['message' => 'Vous ne pouvez pointer que vos propres cours'], 403);
            }

            // Créer ou mettre à jour la présence en ligne
            $attendance = Attendance::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'schedule_id' => $schedule->id,
                    'attendance_date' => now()->toDateString(),
                ],
                [
                    'teacher_id' => $teacher->id,
                    'check_in_time' => now()->toTimeString(),
                    'status' => 'present',
                    'attendance_mode' => 'online_present',
                ]
            );

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'attendance_online_check_in',
                'model' => 'Attendance',
                'model_id' => $attendance->id,
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Présence en ligne enregistrée',
                'attendance' => $attendance,
            ]);
        } catch (\Exception $e) {
            Log::error('CheckInOnline error: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors du pointage en ligne'], 500);
        }
    }

    public function checkOutOnline(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $teacher = Teacher::where('user_id', $user->id)->first();

            if (!$user->hasRole('teacher')) {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            if (!$teacher) {
                return response()->json(['message' => 'Enseignant non trouvé'], 404);
            }

            $validated = $request->validate([
                'schedule_id' => 'required|exists:schedules,id',
            ]);

            $schedule = Schedule::findOrFail($validated['schedule_id']);

            if ($schedule->teacher_id !== $teacher->id) {
                return response()->json(['message' => 'Vous ne pouvez pointer que vos propres cours'], 403);
            }

            // Chercher la présence du jour
            $attendance = Attendance::where('teacher_id', $teacher->id)
                ->where('schedule_id', $schedule->id)
                ->where('attendance_date', now()->toDateString())
                ->first();

            if (!$attendance) {
                return response()->json(['message' => 'Aucun pointage d\'entrée en ligne actif trouvé pour ce cours'], 422);
            }

            // Calculer les heures travaillées
            if ($attendance->check_in_time) {
                $checkInTime = Carbon::parse($attendance->check_in_time);
                $hoursWorked = round(now()->diffInMinutes($checkInTime) / 60, 2);
                $attendance->hours_worked = $hoursWorked;
            }

            $attendance->check_out_time = now()->toTimeString();
            $attendance->save();

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'attendance_online_check_out',
                'model' => 'Attendance',
                'model_id' => $attendance->id,
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Sortie en ligne enregistrée',
                'attendance' => $attendance,
            ]);
        } catch (\Exception $e) {
            Log::error('CheckOutOnline error: ' . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la sortie en ligne'], 500);
        }
    }

    private function getTodaySchedulesForTeacher(Teacher $teacher, Carbon $date): \Illuminate\Database\Eloquent\Collection
    {
        $dayName = $this->getDayName($date);

        return Schedule::where('teacher_id', $teacher->id)
            ->where('day_of_week', $dayName)
            ->where('is_archived', false)
            ->with(['subject', 'classroom', 'teachingType'])
            ->orderBy('start_time')
            ->get();
    }
}
