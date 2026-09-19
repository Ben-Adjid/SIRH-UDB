<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CalculatePayrollRequest;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\Teacher;
use App\Models\Attendance;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\NotificationService;

class PayrollController extends Controller {

    const BRS_RATE = 0.05;
    const BRS_THRESHOLD = 25000;
    const CHECK_THRESHOLD = 100000;

    private function attendanceHours(Teacher $teacher, int $year, int $month): array
    {
        $attendances = Attendance::with('schedule')
            ->where('teacher_id', $teacher->id)
            ->whereYear('attendance_date', $year)
            ->whereMonth('attendance_date', $month)
            ->whereNotIn('status', ['absent'])
            ->whereNotNull('check_in_time')
            ->get();

        $hours = 0.0;
        $incomplete = 0;
        foreach ($attendances as $attendance) {
            if (!$attendance->check_out_time) $incomplete++;
            if ($attendance->schedule?->start_time && $attendance->schedule?->end_time) {
                $hours += Carbon::parse($attendance->schedule->start_time)
                    ->diffInMinutes(Carbon::parse($attendance->schedule->end_time)) / 60;
            } elseif ($attendance->hours_worked !== null) {
                $hours += (float) $attendance->hours_worked;
            }
        }

        return ['hours' => round($hours, 2), 'incomplete' => $incomplete];
    }

    public function attendanceSummary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'teacher_id' => 'required|exists:teachers,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:' . (date('Y') + 1),
        ]);
        $summary = $this->attendanceHours(Teacher::findOrFail($validated['teacher_id']), $validated['year'], $validated['month']);
        return response()->json($summary);
    }

    public function calculate(CalculatePayrollRequest $request): JsonResponse {
        try {
            $validated = $request->validated();

            $teacher = Teacher::findOrFail($validated['teacher_id']);

            if ((float) $teacher->hourly_rate <= 0) {
                return response()->json(['message' => 'L\'enseignant n\'a pas de taux horaire configuré'], 422);
            }

            $attendanceSummary = $this->attendanceHours($teacher, $validated['year'], $validated['month']);
            $requestedHours = (float) ($validated['hours_worked'] ?? 0);
            // Utiliser les heures manuelles si fournies, sinon utiliser les heures du pointage
            $hoursWorked = $requestedHours > 0 ? $requestedHours : $attendanceSummary['hours'];
            if ($hoursWorked <= 0) {
                return response()->json(['message' => 'Aucune heure de travail trouvée. Vérifiez les pointages ou entrez les heures manuellement.'], 422);
            }
            $incompleteAttendances = $attendanceSummary['incomplete'];
            $baseAmount = $hoursWorked * $teacher->hourly_rate;

            $brsAmount = 0;
            if ($baseAmount > self::BRS_THRESHOLD) {
                $brsAmount = round($baseAmount * self::BRS_RATE, 2);
            }

            $manualBonus = (float) ($validated['bonus'] ?? 0);
            $manualDeduction = (float) ($validated['deduction'] ?? 0);
            $totalEarnings = $baseAmount + $manualBonus;
            $totalDeductions = $brsAmount + $manualDeduction;
            $grossSalary = $totalEarnings - $brsAmount;
            $netSalary = max(0, $totalEarnings - $totalDeductions);

            $paymentMethod = $grossSalary >= self::CHECK_THRESHOLD ? 'check' : 'cash';

            return DB::transaction(function () use ($validated, $teacher, $hoursWorked, $incompleteAttendances, $baseAmount, $brsAmount, $grossSalary, $totalEarnings, $totalDeductions, $netSalary, $paymentMethod, $manualBonus, $manualDeduction) {
                Payroll::where([
                    'teacher_id' => $teacher->id,
                    'year' => $validated['year'],
                    'month' => $validated['month'],
                ])->whereIn('status', ['draft', 'calculated'])->delete();

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
                        'hours_worked' => $hoursWorked,
                        'hourly_rate' => $teacher->hourly_rate,
                        'brs_applied' => $brsAmount > 0,
                        'brs_amount' => $brsAmount,
                        'manual_bonus' => $manualBonus,
                        'manual_deduction' => $manualDeduction,
                        'payment_method' => $paymentMethod,
                        'incomplete_attendances' => $incompleteAttendances,
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
            if (!auth()->user()->hasAnyRole(['admin', 'accountant', 'hr_manager', 'secretary'])) {
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

            $details = PayrollDetail::where('payroll_id', $payroll->id)->get();
            $totalEarnings = $details->where('type', 'earning')->sum('amount');
            $totalDeductions = $details->where('type', 'deduction')->sum('amount');
            $netSalary = max(0, $totalEarnings - $totalDeductions);
            $grossSalary = $totalEarnings - $payroll->brs_amount;
            $paymentMethod = $grossSalary >= self::CHECK_THRESHOLD ? 'check' : 'cash';

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
            if ($payroll->status === 'validated' || $payroll->status === 'paid') {
                return response()->json(['message' => 'Paie déjà validée'], 422);
            }
            if ($payroll->status === 'draft') {
                return response()->json(['message' => 'Veuillez d\'abord calculer la paie'], 422);
            }

            $payroll->update(['status' => 'validated', 'validated_by' => auth()->id(), 'validated_at' => now()]);

            AuditLog::create(['user_id' => auth()->id(), 'action' => 'validate_payroll', 'model' => 'Payroll', 'model_id' => $payroll->id, 'ip_address' => request()->ip()]);

            if ($payroll->teacher) {
                NotificationService::send(
                    $payroll->teacher->user_id,
                    'Honoraire validé',
                    'Votre honoraire pour ' . $payroll->month . '/' . $payroll->year . ' a été validé.',
                    'info', '/payroll'
                );
            }

            return response()->json(['message' => 'Validé', 'payroll' => $payroll]);
        } catch (\Exception $e) {
            Log::error('PayrollValidate error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors de la validation'], 500);
        }
    }

    public function markAsPaid(Request $request, Payroll $payroll): JsonResponse {
        try {
            if ($payroll->status !== 'validated') {
                return response()->json(['message' => 'Seules les paies validées peuvent être payées'], 422);
            }

            $payroll->update(['status' => 'paid']);

            $payroll->load('teacher');
            if ($payroll->teacher) {
                NotificationService::send(
                    $payroll->teacher->user_id,
                    'Honoraire payé',
                    'Votre honoraire de ' . number_format((float) $payroll->net_salary, 0, ',', ' ') . ' FCFA pour ' . $payroll->month . '/' . $payroll->year . ' a été payé par ' . ($payroll->payment_method === 'check' ? 'chèque' : 'espèces') . '.',
                    'success',
                    '/payroll'
                );
            }

            AuditLog::create(['user_id' => auth()->id(), 'action' => 'mark_payroll_paid', 'model' => 'Payroll', 'model_id' => $payroll->id, 'ip_address' => request()->ip()]);

            return response()->json([
                'message' => 'Marquée comme payée',
                'payroll' => $payroll,
                'payment_info' => [
                    'method' => $payroll->payment_method,
                    'label' => $payroll->payment_method === 'check' ? 'Chèque' : 'Espèces',
                    'amount' => $payroll->net_salary,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('PayrollMarkAsPaid error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur'], 500);
        }
    }

    public function export(Request $request) {
        try {
            $query = Payroll::with('teacher.user');

            if ($request->filled('month') && $request->filled('year')) {
                $query->where('month', $request->month)->where('year', $request->year);
            }

            $payrolls = $query->orderBy('year', 'desc')->orderBy('month', 'desc')->get();

            $filename = 'export_honoraires_' . now()->format('Y-m-d_H-i-s') . '.csv';
            $headers = [
                'Content-type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=$filename",
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
                    fputcsv($file, [
                        $p->id,
                        $p->teacher?->user?->name ?? 'Inconnu',
                        'vacataire',
                        $p->month . '/' . $p->year,
                        $d['hourly_rate'] ?? '-',
                        $d['hours_worked'] ?? '-',
                        $p->honorarium_amount,
                        $p->brs_amount ?? 0,
                        $p->net_salary,
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
            $query = Payroll::with(['teacher.user', 'teacher.ufr', 'details']);

            if ($user->hasRole('teacher')) {
                $teacher = Teacher::where('user_id', $user->id)->first();
                if (!$teacher) return response()->json(['message' => 'Non autorisé'], 403);
                $query->where('teacher_id', $teacher->id);
            } elseif ($request->filled('teacher_id')) {
                $query->where('teacher_id', $request->teacher_id);
            }

            if ($request->filled('year')) $query->where('year', $request->year);
            if ($request->filled('month')) $query->where('month', $request->month);
            if ($request->filled('status')) $query->where('status', $request->status);

            return response()->json($query->orderBy('year', 'desc')->orderBy('month', 'desc')->paginate($request->input('per_page', 20)));
        } catch (\Exception $e) {
            Log::error('PayrollIndex error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement'], 500);
        }
    }

    public function show(Payroll $payroll): JsonResponse {
        try {
            $user = auth()->user();
            if (!$payroll->teacher || ($user->id !== $payroll->teacher->user_id && !$user->hasRole(['admin', 'accountant', 'hr_manager', 'secretary']))) {
                return response()->json(['message' => 'Non autorisé'], 403);
            }
            return response()->json($payroll->load(['teacher.user', 'teacher.ufr', 'details', 'validator']));
        } catch (\Exception $e) {
            Log::error('PayrollShow error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur'], 500);
        }
    }
}
