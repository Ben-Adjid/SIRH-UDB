<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CalculatePayrollRequest;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\Teacher;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\NotificationService;
use Barryvdh\DomPDF\Facade\Pdf;

class PayrollController extends Controller {

    const BRS_RATE = 0.05;
    const BRS_THRESHOLD = 25000;
    const CHECK_THRESHOLD = 100000;
    const HOURS_PER_DAY = 8;
    const GRACE_PERIOD_MINUTES = 15;

    private function getApprovedLeaveDays(Teacher $teacher, int $year, int $month): array
    {
        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth()->endOfDay();

        $leaves = LeaveRequest::with('leaveType')
            ->where('teacher_id', $teacher->id)
            ->where('status', 'approved')
            ->whereNotNull('approved_at')
            ->where(function ($q) use ($monthStart, $monthEnd) {
                $q->whereBetween('start_date', [$monthStart, $monthEnd])
                  ->orWhereBetween('end_date', [$monthStart, $monthEnd])
                  ->orWhere([['start_date', '<=', $monthStart], ['end_date', '>=', $monthEnd]]);
            })
            ->get();

        $totalDays = 0;
        $leaveDetails = [];
        foreach ($leaves as $leave) {
            if (!$leave->start_date || !$leave->end_date) {
                continue;
            }
            $start = Carbon::parse($leave->start_date)->max($monthStart);
            $end = Carbon::parse($leave->end_date)->min($monthEnd);
            $days = max(0, $start->diffInDays($end) + 1);
            $totalDays += $days;
            $leaveDetails[] = [
                'type' => $leave->leaveType?->name ?? 'Congé',
                'days' => $days,
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d'),
            ];
        }

        return ['total_days' => (int)$totalDays, 'details' => $leaveDetails];
    }

    private function attendanceHours(Teacher $teacher, int $year, int $month): array
    {
        $attendances = Attendance::with('schedule')
            ->where('teacher_id', $teacher->id)
            ->whereYear('attendance_date', $year)
            ->whereMonth('attendance_date', $month)
            ->where('status', '!=', 'absent')
            ->whereNotNull('check_in_time')
            ->get();

        $hours = 0.0;
        $incomplete = 0;
        foreach ($attendances as $attendance) {
            if (!$attendance->check_out_time) {
                $incomplete++;
            }

            if ($attendance->schedule?->start_time && $attendance->schedule?->end_time) {
                try {
                    $dayHours = Carbon::parse($attendance->schedule->start_time)
                        ->diffInMinutes(Carbon::parse($attendance->schedule->end_time)) / 60;

                    if ($attendance->status === 'late' && $attendance->check_in_time) {
                        $lateMinutes = Carbon::parse($attendance->check_in_time)
                            ->diffInMinutes(Carbon::parse($attendance->schedule->start_time));
                        $deductedMinutes = min(abs($lateMinutes), self::GRACE_PERIOD_MINUTES);
                        $dayHours -= ($deductedMinutes / 60);
                    }

                    $hours += max(0, $dayHours);
                } catch (\Exception $e) {
                    Log::warning('attendanceHours: Failed to parse schedule times', [
                        'attendance_id' => $attendance->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            } elseif ($attendance->hours_worked !== null && $attendance->hours_worked > 0) {
                $hours += (float) $attendance->hours_worked;
            }
        }

        return ['hours' => round($hours, 2), 'incomplete' => $incomplete];
    }

    public function attendanceSummary(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'teacher_id' => 'required|exists:teachers,id',
                'month' => 'required|integer|min:1|max:12',
                'year' => 'required|integer|min:2020|max:' . (date('Y') + 1),
            ]);

            $teacher = Teacher::findOrFail($validated['teacher_id']);
            $summary = $this->attendanceHours($teacher, (int)$validated['year'], (int)$validated['month']);
            $leaves = $this->getApprovedLeaveDays($teacher, (int)$validated['year'], (int)$validated['month']);
            $summary['leave_days'] = $leaves['total_days'];
            $summary['leave_hours'] = $leaves['total_days'] * self::HOURS_PER_DAY;
            $summary['hourly_rate'] = (float) $teacher->hourly_rate;

            Log::info('attendanceSummary: Résumé de présence récupéré', [
                'teacher_id' => $validated['teacher_id'],
                'month' => $validated['month'],
                'year' => $validated['year'],
            ]);

            return response()->json([
                'success' => true,
                'summary' => $summary,
                'hours' => $summary['hours'],
                'incomplete' => $summary['incomplete']
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            Log::error('attendanceSummary error: ' . $e->getMessage(), [
                'url' => request()->fullUrl(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement du résumé de présence',
            ], 500);
        }
    }

    public function calculate(CalculatePayrollRequest $request): JsonResponse {
        try {
            $validated = $request->validated();

            $teacher = Teacher::findOrFail($validated['teacher_id']);

            if (!$teacher->hourly_rate || (float) $teacher->hourly_rate <= 0) {
                return response()->json(['message' => 'L\'enseignant n\'a pas de taux horaire configuré'], 422);
            }

            $attendanceSummary = $this->attendanceHours($teacher, $validated['year'], $validated['month']);
            $requestedHours = (float) ($validated['hours_worked'] ?? 0);
            $hoursWorked = $requestedHours > 0 ? $requestedHours : $attendanceSummary['hours'];
            if ($hoursWorked <= 0) {
                return response()->json(['message' => 'Aucune heure de travail trouvée. Vérifiez les pointages ou entrez les heures manuellement.'], 422);
            }
            $incompleteAttendances = $attendanceSummary['incomplete'];

            $approvedLeaves = $this->getApprovedLeaveDays($teacher, $validated['year'], $validated['month']);
            $deductedLeaveDays = $approvedLeaves['total_days'];
            $deductedLeaveHours = $deductedLeaveDays * self::HOURS_PER_DAY;
            $hoursWorked = max(0, $hoursWorked - $deductedLeaveHours);

            $baseAmount = round($hoursWorked * (float)$teacher->hourly_rate, 2);

            $brsAmount = 0;
            if ($baseAmount >= self::BRS_THRESHOLD) {
                $brsAmount = round($baseAmount * self::BRS_RATE, 2);
            }

            $manualBonus = (float) ($validated['bonus'] ?? 0);
            $manualDeduction = (float) ($validated['deduction'] ?? 0);
            $totalEarnings = $baseAmount + $manualBonus;
            $totalDeductions = $brsAmount + $manualDeduction;
            $grossSalary = $totalEarnings;
            $netSalary = max(0, $totalEarnings - $totalDeductions);

            $paymentMethod = $netSalary >= self::CHECK_THRESHOLD ? 'check' : 'cash';

            return DB::transaction(function () use ($validated, $teacher, $hoursWorked, $incompleteAttendances, $baseAmount, $brsAmount, $grossSalary, $totalEarnings, $totalDeductions, $netSalary, $paymentMethod, $manualBonus, $manualDeduction, $approvedLeaves, $deductedLeaveHours, $attendanceSummary) {
                // Create audit logs before deleting draft payrolls
                Payroll::where([
                    'teacher_id' => $teacher->id,
                    'year' => $validated['year'],
                    'month' => $validated['month'],
                ])->whereIn('status', ['draft', 'calculated'])->get()->each(function($payroll) {
                    AuditLog::create([
                        'user_id' => auth()->id(),
                        'action' => 'delete_draft_payroll',
                        'model' => 'Payroll',
                        'model_id' => $payroll->id,
                        'old_values' => $payroll->toArray(),
                        'ip_address' => request()->ip(),
                    ]);
                });

                $existing = Payroll::withTrashed()->where([
                    'teacher_id' => $teacher->id,
                    'year' => $validated['year'],
                    'month' => $validated['month'],
                ])->first();
                if ($existing && !$existing->trashed() && in_array($existing->status, ['validated', 'paid'])) {
                    return response()->json(['message' => 'Cette paie est déjà validée ou payée et ne peut plus être recalculée'], 422);
                }
                // Hard delete: the unique (teacher_id, year, month) index also covers soft-deleted rows.
                $existing?->forceDelete();

                $payroll = Payroll::create([
                    'teacher_id' => $teacher->id,
                    'year' => $validated['year'],
                    'month' => $validated['month'],
                    'base_salary' => 0,
                    'honorarium_amount' => $baseAmount,
                    'brs_amount' => $brsAmount,
                    'gross_salary' => $grossSalary,
                    'deductions' => $totalDeductions,
                    'net_salary' => $netSalary,
                    'payment_method' => $paymentMethod,
                    'status' => 'calculated',
                    'calculation_details' => [
                        'type' => 'vacataire',
                        'hours_attendance' => $attendanceSummary['hours'],
                        'hours_deducted_leaves' => $deductedLeaveHours,
                        'hours_worked' => $hoursWorked,
                        'hourly_rate' => $teacher->hourly_rate,
                        'brs_applied' => $brsAmount > 0,
                        'brs_amount' => $brsAmount,
                        'manual_bonus' => $manualBonus,
                        'manual_deduction' => $manualDeduction,
                        'payment_method' => $paymentMethod,
                        'incomplete_attendances' => $incompleteAttendances,
                        'approved_leaves' => $approvedLeaves,
                    ]
                ]);

                PayrollDetail::create([
                    'payroll_id' => $payroll->id,
                    'description' => 'Honoraire : ' . number_format($hoursWorked, 1) . 'h × ' . number_format($teacher->hourly_rate, 0, ',', ' ') . ' F',
                    'type' => 'earning',
                    'amount' => $baseAmount,
                ]);

                if ($brsAmount > 0) {
                    PayrollDetail::create([
                        'payroll_id' => $payroll->id,
                        'description' => 'BRS (5 % sur ' . number_format($baseAmount, 0, ',', ' ') . ' F)',
                        'type' => 'deduction',
                        'amount' => $brsAmount,
                    ]);
                }

                if ($manualBonus > 0) {
                    PayrollDetail::create([
                        'payroll_id' => $payroll->id,
                        'description' => 'Prime',
                        'type' => 'earning',
                        'amount' => $manualBonus,
                    ]);
                }

                if ($manualDeduction > 0) {
                    PayrollDetail::create([
                        'payroll_id' => $payroll->id,
                        'description' => 'Retenue',
                        'type' => 'deduction',
                        'amount' => $manualDeduction,
                    ]);
                }

                AuditLog::create(['user_id' => auth()->id(), 'action' => 'calculate_payroll', 'model' => 'Payroll', 'model_id' => $payroll->id, 'ip_address' => request()->ip()]);

                return response()->json([
                    'message' => 'Honoraire calculé',
                    'payroll' => $payroll->load('details'),
                    'summary' => [
                        'type' => 'vacataire',
                        'honorarium_amount' => $baseAmount,
                        'base_amount' => $baseAmount,
                        'brs_amount' => $brsAmount,
                        'gross_salary' => $grossSalary,
                        'net_salary' => $netSalary,
                        'payment_method' => $paymentMethod,
                        'payment_label' => $paymentMethod === 'check' ? 'Chèque' : 'Espèces',
                        'incomplete_attendances' => $incompleteAttendances,
                        'approved_leaves_deducted' => $deductedLeaveHours > 0,
                        'approved_leaves_hours' => $deductedLeaveHours,
                        'approved_leaves_details' => $approvedLeaves['details'],
                    ]
                ]);
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Données invalides', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('PayrollCalculate error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du calcul'], 500);
        }
    }

    public function addLineItem(Request $request, Payroll $payroll): JsonResponse {
        try {
            if (!auth()->user()->hasAnyRole(['admin', 'accountant', 'hr_manager'])) {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            if (in_array($payroll->status, ['paid', 'validated'])) {
                return response()->json(['message' => 'Impossible de modifier une paie validée ou payée'], 422);
            }

            $validated = $request->validate([
                'description' => 'required|string|max:255',
                'amount' => 'required|numeric|min:0.01',
                'type' => 'required|in:earning,deduction',
            ]);

            PayrollDetail::create([
                'payroll_id' => $payroll->id,
                'description' => $validated['description'],
                'type' => $validated['type'],
                'amount' => $validated['amount']
            ]);

            // The BRS is already stored as a deduction line by calculate(), so it is included here.
            $details = PayrollDetail::where('payroll_id', $payroll->id)->get();
            $totalEarnings = round((float) $details->where('type', 'earning')->sum('amount'), 2);
            $totalDeductions = round((float) $details->where('type', 'deduction')->sum('amount'), 2);

            $grossSalary = $totalEarnings;
            $netSalary = max(0, $totalEarnings - $totalDeductions);
            $paymentMethod = $netSalary >= self::CHECK_THRESHOLD ? 'check' : 'cash';

            $payroll->update([
                'gross_salary' => $grossSalary,
                'deductions' => $totalDeductions,
                'net_salary' => $netSalary,
                'payment_method' => $paymentMethod,
            ]);

            AuditLog::create(['user_id' => auth()->id(), 'action' => 'add_payroll_line', 'model' => 'Payroll', 'model_id' => $payroll->id, 'ip_address' => $request->ip()]);

            return response()->json(['message' => ($validated['type'] === 'earning' ? 'Prime' : 'Retenue') . ' ajoutée', 'payroll' => $payroll->fresh()->load('details')]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Données invalides', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('PayrollLineItem error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur'], 500);
        }
    }

    public function validatePayroll(Request $request, Payroll $payroll): JsonResponse {
        try {
            if (!auth()->user()->hasAnyRole(['admin', 'accountant', 'hr_manager'])) {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            if (!$payroll->teacher) {
                return response()->json(['message' => 'Enseignant non trouvé'], 404);
            }

            if ($payroll->status === 'validated' || $payroll->status === 'paid') {
                return response()->json(['message' => 'Paie déjà validée'], 422);
            }
            if ($payroll->status === 'draft') {
                return response()->json(['message' => 'Veuillez d\'abord calculer la paie'], 422);
            }

            $payroll->update(['status' => 'validated', 'validated_by' => auth()->id(), 'validated_at' => now()]);

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'validate_payroll',
                'model' => 'Payroll',
                'model_id' => $payroll->id,
                'ip_address' => request()->ip(),
                'new_values' => ['status' => 'validated', 'validated_at' => $payroll->validated_at]
            ]);

            $payroll->loadMissing('teacher.user');
            if ($payroll->teacher?->user) {
                NotificationService::send(
                    $payroll->teacher->user->id,
                    'Honoraire validé',
                    'Votre honoraire pour ' . $payroll->month . '/' . $payroll->year . ' a été validé.',
                    'info', '/payroll'
                );
            }

            return response()->json(['message' => 'Validé', 'payroll' => $payroll->fresh()->load(['teacher.user', 'teacher.ufr', 'details', 'validator'])]);
        } catch (\Exception $e) {
            Log::error('PayrollValidate error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la validation'], 500);
        }
    }

    public function markAsPaid(Request $request, Payroll $payroll): JsonResponse {
        try {
            if (!auth()->user()->hasAnyRole(['admin', 'accountant', 'hr_manager'])) {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            if (!$payroll->teacher) {
                return response()->json(['message' => 'Enseignant non trouvé'], 404);
            }

            if ($payroll->status !== 'validated') {
                return response()->json(['message' => 'Seules les paies validées peuvent être payées'], 422);
            }

            $payroll->update(['status' => 'paid']);

            $payroll->load('teacher.user');
            if ($payroll->teacher?->user) {
                $netSalaryFormatted = number_format((float) ($payroll->net_salary ?? 0), 0, ',', ' ');
                NotificationService::send(
                    $payroll->teacher->user->id,
                    'Honoraire payé',
                    'Votre honoraire de ' . $netSalaryFormatted . ' XOF pour ' . $payroll->month . '/' . $payroll->year . ' a été payé par ' . ($payroll->payment_method === 'check' ? 'chèque' : 'espèces') . '.',
                    'success',
                    '/payroll'
                );
            }

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'mark_payroll_paid',
                'model' => 'Payroll',
                'model_id' => $payroll->id,
                'ip_address' => request()->ip(),
                'new_values' => ['status' => 'paid']
            ]);

            return response()->json([
                'message' => 'Marquée comme payée',
                'payroll' => $payroll->fresh()->load(['teacher.user', 'teacher.ufr', 'details']),
                'payment_info' => [
                    'method' => $payroll->payment_method,
                    'label' => $payroll->payment_method === 'check' ? 'Chèque' : 'Espèces',
                    'amount' => $payroll->net_salary ?? 0,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('PayrollMarkAsPaid error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur'], 500);
        }
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\Response {
        try {
            if (!auth()->user()->hasAnyRole(['admin', 'accountant', 'hr_manager'])) {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            $query = Payroll::with('teacher.user');

            if ($request->filled('month') && $request->filled('year')) {
                $query->where('month', (int)$request->month)->where('year', (int)$request->year);
            }

            $payrolls = $query->orderBy('year', 'desc')->orderBy('month', 'desc')->get();

            if ($payrolls->isEmpty()) {
                return response()->json(['message' => 'Aucune donnée à exporter pour cette période'], 404);
            }

            $filename = 'export_honoraires_' . now()->format('Y-m-d_H-i-s') . '.csv';
            $headers = [
                'Content-type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => "attachment; filename=\"$filename\"",
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Expires' => '0',
            ];

            $columns = ['ID', 'Enseignant', 'Type', 'Mois/Année', 'Taux Horaire', 'Heures', 'Honoraire', 'BRS', 'Net à payer', 'Mode', 'Statut'];

            $callback = function () use ($payrolls, $columns) {
                $file = fopen('php://output', 'w');
                fwrite($file, "\xEF\xBB\xBF");
                fputcsv($file, $columns);

                foreach ($payrolls as $p) {
                    $d = $p->calculation_details ?? [];
                    $method = $p->payment_method === 'check' ? 'Chèque' : 'Espèces';
                    $honorariumAmount = $p->honorarium_amount ?? $p->gross_salary ?? 0;
                    fputcsv($file, [
                        $p->id,
                        $p->teacher?->user?->name ?? 'Inconnu',
                        'vacataire',
                        $p->month . '/' . $p->year,
                        $d['hourly_rate'] ?? '-',
                        $d['hours_worked'] ?? '-',
                        $honorariumAmount,
                        $p->brs_amount ?? 0,
                        $p->net_salary ?? 0,
                        $method,
                        $p->status,
                    ]);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            Log::error('PayrollExport error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de l\'export'], 500);
        }
    }

    public function index(Request $request): JsonResponse {
        try {
            $user = auth()->user();
            $month = (int)$request->input('month', date('m'));
            $year = (int)$request->input('year', date('Y'));
            $page = max(1, (int)$request->input('page', 1));
            $perPage = min(100, max(1, (int)$request->input('per_page', 20)));

            $query = Payroll::with('teacher.user', 'teacher.ufr', 'details')
                ->where('year', $year)
                ->where('month', $month);

            if ($user->hasRole('teacher')) {
                $teacher = Teacher::where('user_id', $user->id)->first();
                if (!$teacher) return response()->json(['message' => 'Non autorisé'], 403);
                $query->where('teacher_id', $teacher->id);
            } elseif ($request->filled('teacher_id')) {
                $query->where('teacher_id', (int)$request->teacher_id);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $byStatus = (clone $query)->toBase()
                ->selectRaw('status, COUNT(*) as count, COALESCE(SUM(net_salary), 0) as net')
                ->groupBy('status')
                ->get()
                ->keyBy('status');
            $summary = ['total_net' => round((float) $byStatus->sum('net'), 2)];
            foreach (['draft', 'calculated', 'validated', 'paid'] as $status) {
                $summary[$status] = (int) ($byStatus[$status]->count ?? 0);
            }

            $query->orderBy('id');

            $paginated = $query->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'current_page' => $paginated->currentPage(),
                'data' => $paginated->items(),
                'from' => $paginated->firstItem(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'to' => $paginated->lastItem(),
                'total' => $paginated->total(),
                'summary' => $summary,
            ]);
        } catch (\Exception $e) {
            Log::error('PayrollIndex error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement'], 500);
        }
    }

    public function show(Payroll $payroll): JsonResponse {
        try {
            $user = auth()->user();

            if (!$payroll->teacher) {
                return response()->json(['message' => 'Enseignant non trouvé'], 404);
            }

            if ($user->hasRole('teacher')) {
                $teacher = Teacher::where('user_id', $user->id)->first();
                if (!$teacher || $teacher->id !== $payroll->teacher_id) {
                    return response()->json(['message' => 'Non autorisé'], 403);
                }
            } elseif (!$user->hasAnyRole(['admin', 'accountant', 'hr_manager'])) {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            return response()->json($payroll->load(['teacher.user', 'teacher.ufr', 'details', 'validator']));
        } catch (\Exception $e) {
            Log::error('PayrollShow error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur'], 500);
        }
    }

    public function calculateMonth(Request $request): JsonResponse {
        try {
            if (!auth()->user()->hasAnyRole(['admin', 'accountant', 'hr_manager'])) {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            $validated = $request->validate([
                'month' => 'required|integer|min:1|max:12',
                'year' => 'required|integer|min:2020|max:' . (date('Y') + 1),
            ]);

            $calculatedCount = DB::transaction(function () use ($validated) {
                $teachers = Teacher::where('status', 'active')->get();
                $calculatedCount = 0;
                $errors = [];

                foreach ($teachers as $teacher) {
                    try {
                        $attendanceSummary = $this->attendanceHours($teacher, $validated['year'], $validated['month']);
                        $approvedLeaves = $this->getApprovedLeaveDays($teacher, $validated['year'], $validated['month']);
                        $deductedLeaveHours = $approvedLeaves['total_days'] * self::HOURS_PER_DAY;
                        $hoursWorked = max(0, $attendanceSummary['hours'] - $deductedLeaveHours);

                        if ($hoursWorked <= 0) continue;

                        if (!$teacher->hourly_rate || (float)$teacher->hourly_rate <= 0) continue;

                        $baseAmount = round($hoursWorked * (float)$teacher->hourly_rate, 2);
                        $brsAmount = 0;
                        if ($baseAmount >= self::BRS_THRESHOLD) {
                            $brsAmount = round($baseAmount * self::BRS_RATE, 2);
                        }

                        $grossSalary = $baseAmount;
                        $netSalary = max(0, $baseAmount - $brsAmount);
                        $paymentMethod = $netSalary >= self::CHECK_THRESHOLD ? 'check' : 'cash';

                        $existing = Payroll::withTrashed()->where([
                            'teacher_id' => $teacher->id,
                            'year' => $validated['year'],
                            'month' => $validated['month'],
                        ])->first();
                        if ($existing && !$existing->trashed() && in_array($existing->status, ['validated', 'paid'])) continue;
                        $existing?->forceDelete();

                        Payroll::create([
                            'teacher_id' => $teacher->id,
                            'year' => $validated['year'],
                            'month' => $validated['month'],
                            'base_salary' => 0,
                            'honorarium_amount' => $baseAmount,
                            'brs_amount' => $brsAmount,
                            'gross_salary' => $grossSalary,
                            'deductions' => 0,
                            'net_salary' => $netSalary,
                            'payment_method' => $paymentMethod,
                            'status' => 'calculated',
                            'calculation_details' => [
                                'type' => 'vacataire',
                                'hours_attendance' => $attendanceSummary['hours'],
                                'hours_deducted_leaves' => $deductedLeaveHours,
                                'hours_worked' => $hoursWorked,
                                'hourly_rate' => (float)$teacher->hourly_rate,
                                'brs_applied' => $brsAmount > 0,
                                'brs_amount' => $brsAmount,
                                'payment_method' => $paymentMethod,
                                'approved_leaves' => $approvedLeaves,
                            ]
                        ]);

                        $calculatedCount++;
                    } catch (\Exception $e) {
                        $teacherName = $teacher->user?->name ?? "Enseignant {$teacher->id}";
                        Log::warning("PayrollCalculateMonth: Erreur pour {$teacherName}", [
                            'teacher_id' => $teacher->id,
                            'error' => $e->getMessage(),
                        ]);
                        $errors[] = "Erreur pour {$teacherName}: " . $e->getMessage();
                    }
                }

                AuditLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'calculate_month_payroll',
                    'model' => 'Payroll',
                    'ip_address' => request()->ip(),
                    'new_values' => ['calculated_count' => $calculatedCount, 'month' => $validated['month'], 'year' => $validated['year']],
                ]);

                return $calculatedCount;
            });

            return response()->json([
                'message' => "Calcul automatique terminé",
                'calculated' => $calculatedCount,
                'total_teachers' => Teacher::where('status', 'active')->count(),
                'errors' => [],
            ]);
        } catch (\Exception $e) {
            Log::error('PayrollCalculateMonth error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du calcul'], 500);
        }
    }

    public function downloadPDF($id): \Symfony\Component\HttpFoundation\Response
    {
        try {
            $user = auth()->user();

            if (!$user->hasAnyRole(['admin', 'accountant', 'hr_manager'])) {
                return response()->json(['message' => 'Non autorisé'], 403);
            }

            $payroll = Payroll::with(['teacher.user', 'details'])->findOrFail($id);

            $teacher = $payroll->teacher;
            if (!$teacher) {
                return response()->json(['message' => 'Enseignant non trouvé'], 404);
            }

            if (!$teacher->user) {
                return response()->json(['message' => 'Données d\'utilisateur incomplètes'], 404);
            }

            $logoPath = public_path('spa/logo.jpg');
            $logoBase64 = '';
            if (file_exists($logoPath)) {
                try {
                    $logoBase64 = 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logoPath));
                } catch (\Exception $e) {
                    Log::warning('PDF logo loading failed', ['error' => $e->getMessage()]);
                }
            }

            $data = [
                'payroll' => $payroll,
                'teacher_name' => $teacher->user->name ?? 'Enseignant',
                'employee_id' => $teacher->employee_id ?? 'N/A',
                'month' => $payroll->month,
                'year' => $payroll->year,
                'gross_salary' => $payroll->gross_salary ?? 0,
                'deductions' => $payroll->deductions ?? 0,
                'net_salary' => $payroll->net_salary ?? 0,
                'details' => $payroll->details ?? [],
                'generated_at' => now()->format('d/m/Y H:i'),
                'logo' => $logoBase64,
            ];

            $pdf = PDF::loadView('payroll.bulletin', $data)
                ->setPaper('a4')
                ->setOptions([
                    'defaultFont' => 'Helvetica',
                    'isHtml5ParserEnabled' => true,
                ]);

            return $pdf->download("bulletin_paie_{$payroll->month}_{$payroll->year}.pdf");
        } catch (\Exception $e) {
            Log::error('PDF generation error', [
                'payroll_id' => $id ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Erreur lors de la génération du PDF'], 500);
        }
    }
}
