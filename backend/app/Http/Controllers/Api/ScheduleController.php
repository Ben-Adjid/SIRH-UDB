<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScheduleRequest;
use App\Models\Schedule;
use App\Models\Teacher;
use App\Models\Ufr;
use App\Models\User;
use App\Models\Subject;
use App\Services\ScheduleConflictService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ScheduleController extends Controller {

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

    private function canAccessSchedule(Schedule $schedule, User $user): bool
    {
        return $user->hasRole('admin')
            || in_array($schedule->teacher?->ufr_id, $this->visibleUfrIds($user));
    }

    private function subjectBelongsToVisibleUfr(int $subjectId, User $user): bool
    {
        if ($user->hasRole('admin')) return true;
        $ufrIds = $this->visibleUfrIds($user);
        return Subject::whereKey($subjectId)
            ->where(fn($q) => $q->whereIn('ufr_id', $ufrIds)
                ->orWhereHas('schedules.teacher', fn($teacher) => $teacher->whereIn('ufr_id', $ufrIds)))
            ->exists();
    }
    
    public function index(Request $request): JsonResponse {
        try {
            $user = auth()->user();
            $query = Schedule::with(['teacher.user', 'teacher.ufr', 'subject', 'teachingType', 'classroom', 'academicYear']);

            if (!$request->boolean('include_archived')) {
                $query->where('is_archived', false);
            }

            if ($user->hasRole('teacher')) {
                $teacher = Teacher::where('user_id', $user->id)->first();
                if (!$teacher) {
                    return response()->json([
                        'success' => true,
                        'data' => [],
                        'pagination' => [
                            'total' => 0,
                            'per_page' => 25,
                            'current_page' => 1,
                            'last_page' => 1,
                            'has_more' => false,
                        ]
                    ]);
                }
                $query->where('teacher_id', $teacher->id);
            } else {
                if ($request->filled('teacher_id')) {
                    $query->where('teacher_id', $request->teacher_id);
                }
            }

            if ($user->hasRole('academic_manager') && !$user->hasRole('admin')) {
                $query->whereHas('teacher', fn($teacher) => $teacher->whereIn('ufr_id', $this->visibleUfrIds($user)));
            }

            if ($request->filled('subject_id')) {
                $query->where('subject_id', $request->subject_id);
            }

            if ($request->filled('academic_year_id')) {
                $query->where('academic_year_id', $request->academic_year_id);
            }

            if ($request->filled('ufr_id')) {
                $query->whereHas('teacher', function ($q) use ($request) {
                    $q->where('ufr_id', $request->ufr_id);
                });
            }

            if ($request->filled('level')) {
                $query->where('level', $request->level);
            }

            if ($request->filled('semester')) {
                $query->where('semester', $request->semester);
            }

            if ($request->filled('type')) {
                $query->where('type', $request->type);
            } else {
                $query->where(function ($q) {
                    $q->whereNull('type')->orWhere('type', '!=', 'exam');
                });
            }

            return response()->json($query->latest()->paginate(min(max((int) $request->input('per_page', 25), 1), 500)));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('ScheduleIndex error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement'], 500);
        }
    }

    public function show(Schedule $schedule): JsonResponse {
        try {
            $schedule->load('teacher');
            if (!$this->canAccessSchedule($schedule, auth()->user())) {
                return response()->json(['message' => 'Vous ne pouvez consulter que les emplois du temps de votre UFR'], 403);
            }
            return response()->json($schedule->load(['teacher.user', 'teacher.ufr', 'subject', 'teachingType', 'classroom', 'academicYear']));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('ScheduleShow error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement'], 500);
        }
    }

    public function store(StoreScheduleRequest $request): JsonResponse {
        try {
            if (!auth()->user()->hasAnyRole(['admin', 'academic_manager', 'hr_manager', 'secretary', 'teacher'])) {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            $validated = $request->validated();

            if (auth()->user()->hasRole('teacher')) {
                $ownTeacher = Teacher::where('user_id', auth()->id())->first();
                if (!$ownTeacher || (int) $validated['teacher_id'] !== (int) $ownTeacher->id) {
                    return response()->json(['message' => 'Un enseignant ne peut programmer que son propre emploi du temps'], 403);
                }
            }

            if (auth()->user()->hasRole('academic_manager') && !auth()->user()->hasRole('admin')) {
                $teacher = Teacher::find($validated['teacher_id']);
                if (!$teacher || !in_array($teacher->ufr_id, $this->visibleUfrIds(auth()->user()), true)) {
                    return response()->json(['message' => 'Vous ne pouvez programmer un cours que dans votre UFR'], 403);
                }
                if (!$this->subjectBelongsToVisibleUfr((int) $validated['subject_id'], auth()->user())) {
                    return response()->json(['message' => 'Vous ne pouvez utiliser qu’une matière de votre UFR'], 403);
                }
            }

            $validated['status'] = $validated['status'] ?? 'scheduled';

            if (empty($validated['academic_year_id'])) {
                $activeYear = \App\Models\AcademicYear::where('is_active', true)->first();
                if ($activeYear) {
                    $validated['academic_year_id'] = $activeYear->id;
                }
            }

            if (empty($validated['start_date'])) {
                $validated['start_date'] = now()->toDateString();
            }
            if (empty($validated['end_date'])) {
                $validated['end_date'] = $validated['start_date'];
            }

            $conflictService = app(ScheduleConflictService::class);
            if ($conflictService->hasConflict($validated['teacher_id'], $validated['day_of_week'], $validated['start_time'], $validated['end_time'], classroomId: $validated['classroom_id'], academicYearId: $validated['academic_year_id'] ?? null)) {
                return response()->json(['message' => 'Conflit de salle ou d\'enseignant sur ce créneau'], 422);
            }

            $schedule = Schedule::create($validated);

            \App\Models\AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'create_schedule',
                'model' => 'Schedule',
                'model_id' => $schedule->id,
                'new_values' => $validated,
                'ip_address' => $request->ip(),
            ]);

            $teacher = \App\Models\Teacher::with('user')->find($validated['teacher_id']);
            if ($teacher && $teacher->user) {
                $subject = \App\Models\Subject::find($validated['subject_id']);
                \App\Services\NotificationService::send(
                    $teacher->user->id,
                    'Nouveau cours programmé',
                    'Un cours de "' . ($subject->name ?? 'N/A') . '" a été programmé le ' . $validated['day_of_week'] . ' de ' . $validated['start_time'] . ' à ' . $validated['end_time'],
                    'info',
                    '/schedules'
                );
            }

            return response()->json($schedule->load(['teacher.user', 'subject', 'teachingType', 'classroom', 'academicYear']), 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Données invalides', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('ScheduleStore error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la création'], 500);
        }
    }

    public function update(Request $request, Schedule $schedule): JsonResponse {
        try {
            if (!auth()->user()->hasAnyRole(['admin', 'academic_manager', 'hr_manager', 'secretary'])) {
                return response()->json(['message' => 'Non autorisé'], 403);
            }
            $schedule->load('teacher');
            if (!$this->canAccessSchedule($schedule, auth()->user())) {
                return response()->json(['message' => 'Vous ne pouvez modifier que les emplois du temps de votre UFR'], 403);
            }

            $validated = $request->validate([
                'teacher_id' => 'sometimes|required|exists:teachers,id',
                'subject_id' => 'sometimes|required|exists:subjects,id',
                'teaching_type_id' => 'nullable|exists:teaching_types,id',
                'classroom_id' => 'sometimes|required|exists:classrooms,id',
                'level' => 'sometimes|required|in:L1,L2,L3,M1,M2',
                'semester' => 'nullable|in:S1,S2',
                'academic_year_id' => 'sometimes|exists:academic_years,id',
                'day_of_week' => 'sometimes|required|string|in:Lundi,Mardi,Mercredi,Jeudi,Vendredi,Samedi,Dimanche',
                'start_time' => 'sometimes|required|date_format:H:i',
                'end_time' => 'sometimes|required|date_format:H:i|after:start_time',
                'status' => 'sometimes|required|in:scheduled,cancelled,completed',
                'start_date' => 'sometimes|required|date',
                'end_date' => 'sometimes|nullable|date|after_or_equal:start_date',
            ]);

            if (auth()->user()->hasRole('academic_manager') && !auth()->user()->hasRole('admin')
                && isset($validated['subject_id'])
                && !$this->subjectBelongsToVisibleUfr((int) $validated['subject_id'], auth()->user())) {
                return response()->json(['message' => 'Vous ne pouvez utiliser qu’une matière de votre UFR'], 403);
            }

            $classroom_id = $validated['classroom_id'] ?? $schedule->classroom_id;
            $day_of_week = $validated['day_of_week'] ?? $schedule->day_of_week;
            $start_time = $validated['start_time'] ?? $schedule->start_time;
            $end_time = $validated['end_time'] ?? $schedule->end_time;
            $teacher_id = $validated['teacher_id'] ?? $schedule->teacher_id;
            $academic_year_id = $validated['academic_year_id'] ?? $schedule->academic_year_id;

            $conflictService = app(ScheduleConflictService::class);
            if ($conflictService->hasConflict($teacher_id, $day_of_week, $start_time, $end_time, excludeId: $schedule->id, classroomId: $classroom_id, academicYearId: $academic_year_id)) {
                return response()->json(['message' => 'Conflit de salle ou d\'enseignant sur ce créneau'], 422);
            }

            $schedule->update($validated);

            \App\Models\AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'update_schedule',
                'model' => 'Schedule',
                'model_id' => $schedule->id,
                'new_values' => $validated,
                'ip_address' => $request->ip(),
            ]);

            $teacher = \App\Models\Teacher::with('user')->find($schedule->teacher_id);
            if ($teacher && $teacher->user) {
                \App\Services\NotificationService::send(
                    $teacher->user->id,
                    'Cours modifié',
                    'Votre cours du ' . $schedule->day_of_week . ' a été modifié. Vérifiez les changements dans l\'emploi du temps.',
                    'warning',
                    '/schedules'
                );
            }

            return response()->json($schedule->load(['teacher.user', 'subject', 'teachingType', 'classroom', 'academicYear']));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Données invalides', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('ScheduleUpdate error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la mise à jour'], 500);
        }
    }

    public function destroy(Request $request, Schedule $schedule): JsonResponse {
        try {
            if (!auth()->user()->hasAnyRole(['admin', 'academic_manager', 'hr_manager', 'secretary'])) {
                return response()->json(['message' => 'Non autorisé'], 403);
            }
            $schedule->load('teacher');
            if (!$this->canAccessSchedule($schedule, auth()->user())) {
                return response()->json(['message' => 'Vous ne pouvez supprimer que les emplois du temps de votre UFR'], 403);
            }
            $teacher = \App\Models\Teacher::with('user')->find($schedule->teacher_id);
            if ($teacher && $teacher->user) {
                \App\Services\NotificationService::send(
                    $teacher->user->id,
                    'Cours supprimé',
                    'Votre cours du ' . $schedule->day_of_week . ' (' . ($schedule->subject->name ?? 'N/A') . ') a été supprimé de l\'emploi du temps.',
                    'danger',
                    '/schedules'
                );
            }

            \App\Models\AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'delete_schedule',
                'model' => 'Schedule',
                'model_id' => $schedule->id,
                'old_values' => $schedule->toArray(),
                'ip_address' => $request->ip(),
            ]);

            $schedule->delete();
            return response()->json(['message' => 'Emploi du temps supprimé']);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('ScheduleDestroy error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la suppression'], 500);
        }
    }

    public function storeExam(Request $request): JsonResponse {
        try {
            if (!auth()->user()->hasAnyRole(['admin', 'academic_manager', 'hr_manager', 'secretary'])) {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            $validated = $request->validate([
                'teacher_id' => 'required|exists:teachers,id',
                'subject_id' => 'required|exists:subjects,id',
                'classroom_id' => 'required|exists:classrooms,id',
                'level' => 'required|in:L1,L2,L3,M1,M2',
                'semester' => 'nullable|in:S1,S2',
                'academic_year_id' => 'nullable|exists:academic_years,id',
                'day_of_week' => 'required|string|in:Lundi,Mardi,Mercredi,Jeudi,Vendredi,Samedi,Dimanche',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'start_date' => 'required|date',
                'exam_type' => 'required|in:DS,Examen,Rattrapage,midterm,final,quiz',
                'coefficient' => 'nullable|numeric|min:0',
            ]);

            if (auth()->user()->hasRole('academic_manager') && !auth()->user()->hasRole('admin')) {
                $teacher = Teacher::find($validated['teacher_id']);
                if (!$teacher || !in_array($teacher->ufr_id, $this->visibleUfrIds(auth()->user()), true)) {
                    return response()->json(['message' => 'Vous ne pouvez programmer un examen que dans votre UFR'], 403);
                }
                if (!$this->subjectBelongsToVisibleUfr((int) $validated['subject_id'], auth()->user())) {
                    return response()->json(['message' => 'Vous ne pouvez utiliser qu’une matière de votre UFR'], 403);
                }
            }

            if (empty($validated['academic_year_id'])) {
                $activeYear = \App\Models\AcademicYear::where('is_active', true)->first();
                if ($activeYear) {
                    $validated['academic_year_id'] = $activeYear->id;
                }
            }

            $conflictService = app(ScheduleConflictService::class);
            if ($conflictService->hasConflict($validated['teacher_id'], $validated['day_of_week'], $validated['start_time'], $validated['end_time'], classroomId: $validated['classroom_id'], academicYearId: $validated['academic_year_id'] ?? null)) {
                return response()->json(['message' => 'Conflit de salle ou d\'enseignant sur ce créneau'], 422);
            }

            $exam = Schedule::create([
                'teacher_id' => $validated['teacher_id'],
                'subject_id' => $validated['subject_id'],
                'classroom_id' => $validated['classroom_id'],
                'level' => $validated['level'],
                'semester' => $validated['semester'] ?? null,
                'academic_year_id' => $validated['academic_year_id'] ?? null,
                'day_of_week' => $validated['day_of_week'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'start_date' => $validated['start_date'],
                'status' => 'scheduled',
                'type' => 'exam',
                'exam_type' => $validated['exam_type'],
                'coefficient' => $validated['coefficient'] ?? 1,
            ]);

            return response()->json($exam->load(['teacher.user', 'subject', 'classroom', 'academicYear']), 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Données invalides', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('ScheduleStoreExam error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la création de l\'examen'], 500);
        }
    }

    public function exams(Request $request): JsonResponse {
        try {
            $query = Schedule::with(['teacher.user', 'teacher.ufr', 'subject', 'classroom', 'academicYear'])
                ->where('type', 'exam');

            if (auth()->user()->hasRole('academic_manager') && !auth()->user()->hasRole('admin')) {
                $query->whereHas('teacher', fn($teacher) => $teacher->whereIn('ufr_id', $this->visibleUfrIds(auth()->user())));
            }

            if (!$request->boolean('include_archived')) {
                $query->where('is_archived', false);
            }

            if ($request->filled('subject_id')) {
                $query->where('subject_id', $request->subject_id);
            }

            if ($request->filled('academic_year_id')) {
                $query->where('academic_year_id', $request->academic_year_id);
            }

            if ($request->filled('ufr_id')) {
                $query->whereHas('teacher', function ($q) use ($request) {
                    $q->where('ufr_id', $request->ufr_id);
                });
            }

            return response()->json($query->latest()->paginate(min(max((int) $request->input('per_page', 25), 1), 500)));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('ScheduleExams error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des examens'], 500);
        }
    }

    public function updateExam(Request $request, Schedule $schedule): JsonResponse
    {
        try {
            if (!auth()->user()->hasAnyRole(['admin', 'academic_manager'])) {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            if ($schedule->type !== 'exam') {
                return response()->json(['message' => 'Examen introuvable'], 404);
            }
        $schedule->load('teacher');
        if (!$this->canAccessSchedule($schedule, auth()->user())) {
            return response()->json(['message' => 'Vous ne pouvez modifier que les examens de votre UFR'], 403);
        }

        $validated = $request->validate([
            'classroom_id' => 'sometimes|required|exists:classrooms,id',
            'level' => 'sometimes|required|in:L1,L2,L3,M1,M2',
            'day_of_week' => 'sometimes|required|string|in:Lundi,Mardi,Mercredi,Jeudi,Vendredi,Samedi,Dimanche',
            'start_time' => 'sometimes|required|date_format:H:i',
            'end_time' => 'sometimes|required|date_format:H:i|after:start_time',
            'start_date' => 'sometimes|required|date',
            'exam_type' => 'sometimes|required|in:DS,Examen,Rattrapage,midterm,final,quiz',
            'status' => 'sometimes|required|in:scheduled,cancelled,completed',
        ]);

            $schedule->update($validated);

            Log::info('updateExam: Examen modifié', ['schedule_id' => $schedule->id]);

            return response()->json([
                'success' => true,
                'message' => 'Examen modifié',
                'exam' => $schedule->fresh()->load(['teacher.user', 'subject', 'classroom', 'academicYear'])
            ]);
        } catch (\Throwable $e) {
            Log::error('updateExam error: ' . $e->getMessage(), [
                'url' => request()->fullUrl(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la modification de l\'examen',
            ], 500);
        }
    }

    public function destroyExam(Schedule $schedule): JsonResponse
    {
        try {
            if (!auth()->user()->hasAnyRole(['admin', 'academic_manager'])) {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            if ($schedule->type !== 'exam') {
                return response()->json(['message' => 'Examen introuvable'], 404);
            }
        $schedule->load('teacher');
        if (!$this->canAccessSchedule($schedule, auth()->user())) {
            return response()->json(['message' => 'Vous ne pouvez supprimer que les examens de votre UFR'], 403);
        }

            $schedule->delete();

            Log::info('destroyExam: Examen supprimé', ['schedule_id' => $schedule->id]);

            return response()->json([
                'success' => true,
                'message' => 'Examen supprimé'
            ]);
        } catch (\Throwable $e) {
            Log::error('destroyExam error: ' . $e->getMessage(), [
                'url' => request()->fullUrl(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de l\'examen',
            ], 500);
        }
    }
}
