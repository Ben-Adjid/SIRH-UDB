<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\Payroll;
use App\Models\Schedule;
use App\Models\Homework;
use App\Models\LeaveBalance;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Subject;
use App\Models\Classroom;
use App\Models\Ufr;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller {

    private function academicVisibleUfrIds(User $user): array
    {
        if ($user->hasRole('admin')) return [];
        $ids = Ufr::where('head_id', $user->id)->pluck('id');
        $teacherUfrId = Teacher::where('user_id', $user->id)->value('ufr_id');
        if ($teacherUfrId) $ids->push($teacherUfrId);
        return $ids->unique()->values()->all();
    }

    public function adminStats(): JsonResponse {
        try {
            $today = Carbon::today()->toDateString();
            $startOfWeek = Carbon::now()->startOfWeek()->toDateString();
            $startOfMonth = Carbon::now()->startOfMonth()->toDateString();

            $attendanceTrend = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::today()->subDays($i);
                $totalDay = Attendance::whereDate('attendance_date', $date->toDateString())->count();
                $presentDay = Attendance::whereDate('attendance_date', $date->toDateString())->where('status', 'present')->count();
                $lateDay = Attendance::whereDate('attendance_date', $date->toDateString())->where('status', 'late')->count();
                $attendanceTrend[] = [
                    'day' => $date->translatedFormat('D'),
                    'total' => $totalDay,
                    'present' => $presentDay,
                    'late' => $lateDay,
                ];
            }

            $payrollTrend = [];
            for ($i = 5; $i >= 0; $i--) {
                $date = Carbon::today()->subMonths($i);
                $total = Payroll::where('year', $date->year)
                    ->where('month', $date->month)
                    ->where('status', 'paid')
                    ->sum('net_salary');
                $payrollTrend[] = [
                    'month' => $date->translatedFormat('M'),
                    'amount' => (float)$total
                ];
            }

            $todayPresent = Attendance::where('attendance_date', $today)->where('status', 'present')->count();
            $todayLate = Attendance::where('attendance_date', $today)->where('status', 'late')->count();
            $todayAbsent = Attendance::where('attendance_date', $today)->where('status', 'absent')->count();
            $todayTotal = Attendance::where('attendance_date', $today)->count();
            $attendanceRate = $todayTotal > 0 ? round((($todayPresent + $todayLate) / $todayTotal) * 100, 1) : 0;

            $monthNet = Payroll::where('year', now()->year)->where('month', now()->month)->sum('net_salary');
            $monthGross = Payroll::where('year', now()->year)->where('month', now()->month)->sum('gross_salary');

            $activeUsers = User::where('status', 'active')->count();
            $totalUsers = User::count();
            $monthAbsences = Attendance::whereYear('attendance_date', now()->year)->whereMonth('attendance_date', now()->month)->whereIn('status', ['absent', 'late'])->count();
            $monthTotalAttendance = Attendance::whereYear('attendance_date', now()->year)->whereMonth('attendance_date', now()->month)->count();
            $todayByStatus = Attendance::whereDate('attendance_date', $today)
                ->selectRaw("status, COUNT(*) as count")
                ->groupBy('status')
                ->pluck('count', 'status');

            return response()->json([
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'inactive_users' => $totalUsers - $activeUsers,
                'total_teachers' => Teacher::count(),
                'active_teachers' => Teacher::where('status', 'active')->count(),
                'teachers_on_leave' => 0,
                'teachers_inactive' => Teacher::where('status', 'inactive')->count(),
                'total_ufrs' => Ufr::count(),
                'total_subjects' => Subject::count(),
                'total_classrooms' => Classroom::count(),
                'pending_homeworks' => Homework::where('status', 'planned')->count(),
                'today_present' => $todayPresent,
                'today_late' => $todayLate,
                'today_absent' => $todayAbsent,
                'today_total' => $todayTotal,
                'today_by_status' => $todayByStatus,
                'attendance_rate' => $attendanceRate,
                'month_absences' => $monthAbsences,
                'month_total_attendance' => $monthTotalAttendance,
                'month_attendance_rate' => $monthTotalAttendance > 0 ? round((($monthTotalAttendance - $monthAbsences) / $monthTotalAttendance) * 100, 1) : 0,
                'month_net_payroll' => (float)$monthNet,
                'month_gross_payroll' => (float)$monthGross,
                'honoraires' => Teacher::selectRaw("COALESCE(hourly_rate, 0) as hourly_rate, COUNT(*) as count")
                    ->groupBy('hourly_rate')->get()->pluck('count', 'hourly_rate'),
                'recent_audits' => AuditLog::with('user:id,name')->latest()->take(8)->get(['id', 'action', 'model', 'model_id', 'user_id', 'created_at']),
                'recent_notifications' => \App\Models\Notification::where('user_id', auth()->id())->latest()->take(5)->get(),
                'payroll_status' => (object)Payroll::where('year', now()->year)->where('month', now()->month)->pluck('status', 'teacher_id')->toArray(),
                'recent_teachers' => Teacher::with('user:id,name,email,status')->latest()->take(6)->get(['id', 'user_id', 'employee_id', 'hourly_rate', 'status']),
                'charts' => [
                    'attendance' => $attendanceTrend,
                    'payroll' => $payrollTrend,
                ]
            ]);
        } catch (\Throwable $e) {
            Log::error('AdminStats error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des données'], 500);
        }
    }

    public function hrStats(): JsonResponse {
        try {
            $today = Carbon::today()->toDateString();
            $startOfMonth = Carbon::now()->startOfMonth()->toDateString();

            $absenceTrend = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::today()->subDays($i);
                $absences = Attendance::whereDate('attendance_date', $date->toDateString())->where('status', 'absent')->count();
                $lates = Attendance::whereDate('attendance_date', $date->toDateString())->where('status', 'late')->count();
                $absenceTrend[] = [
                    'label' => $date->translatedFormat('D'),
                    'absent' => $absences,
                    'late' => $lates,
                    'total' => $absences + $lates,
                ];
            }

            $pendingRequests = LeaveRequest::where('status', 'pending')
                ->with(['user', 'teacher.user', 'leaveType'])
                ->latest()
                ->take(10)
                ->get();

            $todayPresent = Attendance::where('attendance_date', $today)->where('status', 'present')->count();
            $todayLate = Attendance::where('attendance_date', $today)->where('status', 'late')->count();
            $todayAbsent = Attendance::where('attendance_date', $today)->where('status', 'absent')->count();
            $todayTotal = Attendance::where('attendance_date', $today)->count();

            $monthAbsent = Attendance::where('attendance_date', '>=', $startOfMonth)->where('status', 'absent')->count();
            $monthLate = Attendance::where('attendance_date', '>=', $startOfMonth)->where('status', 'late')->count();
            $monthTotal = Attendance::where('attendance_date', '>=', $startOfMonth)->count();
            $monthRate = $monthTotal > 0 ? round((($monthTotal - $monthAbsent - $monthLate) / $monthTotal) * 100, 1) : 0;

            return response()->json([
                'teachers_active' => Teacher::where('status', 'active')->count(),
                'teachers_total' => Teacher::count(),
                'pending_leave_requests' => $pendingRequests,
                'pending_leave_count' => LeaveRequest::where('status', 'pending')->count(),
                'attendance_today' => $todayTotal,
                'today_present' => $todayPresent,
                'today_late' => $todayLate,
                'today_absent' => $todayAbsent,
                'month_attendance_rate' => $monthRate,
                'honoraires' => Teacher::selectRaw('hourly_rate, COUNT(*) as count')->whereNotNull('hourly_rate')->groupBy('hourly_rate')->get(),
                'ufr_distribution' => Ufr::withCount('teachers')->get(),
                'recent_attendances' => Attendance::with('teacher.user', 'user')
                    ->where('attendance_date', $today)
                    ->latest()
                    ->take(10)
                    ->get(),
                'charts' => [
                    'absences' => $absenceTrend
                ]
            ]);
        } catch (\Throwable $e) {
            Log::error('HrStats error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des données'], 500);
        }
    }

    public function teacherStats(): JsonResponse {
        try {
            $user = auth()->user();
            $teacher = Teacher::where('user_id', $user->id)->first();

            if (!$teacher) {
                return response()->json([
                    'my_schedules' => [],
                    'my_homeworks' => [],
                    'my_attendance_month' => ['present' => 0, 'absent' => 0, 'late' => 0, 'total_days' => 0],
                    'my_leave_balances' => [],
                    'last_payslip' => null,
                    'volume_hours_remaining' => null,
                    'attendance_this_week' => [],
                    'contract_progress' => ['worked_hours' => 0, 'total_hours' => 0, 'percentage' => 0],
                    'estimated_honoraria' => 0,
                    'next_course' => null,
                ]);
            }

            $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
            $startOfWeek = Carbon::now()->startOfWeek()->toDateString();
            $dayNames = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
            $todayDayName = $dayNames[Carbon::now()->dayOfWeekIso - 1];

            $weekAttendances = Attendance::where('user_id', $user->id)
                ->where('attendance_date', '>=', $startOfWeek)
                ->orderBy('attendance_date')
                ->get();

            $nextSchedules = Schedule::where('teacher_id', $teacher->id)
                ->with(['subject', 'classroom'])
                ->where('day_of_week', $todayDayName)
                ->orderBy('start_time')
                ->get();

            if ($nextSchedules->isEmpty()) {
                $todayIdx = Carbon::now()->dayOfWeekIso - 1;
                for ($i = 1; $i <= 6; $i++) {
                    $nextIdx = ($todayIdx + $i) % 7;
                    $nextDay = $dayNames[$nextIdx];
                    $nextSchedules = Schedule::where('teacher_id', $teacher->id)
                        ->where('day_of_week', $nextDay)
                        ->with(['subject', 'classroom'])
                        ->orderBy('start_time')
                        ->get();
                    if ($nextSchedules->isNotEmpty()) break;
                }
            }

            // KPI 1: Progression du contrat (heures travaillées ce mois / volume_hours)
            $workedHoursMonth = (float) Attendance::where('teacher_id', $teacher->id)
                ->whereYear('attendance_date', Carbon::now()->year)
                ->whereMonth('attendance_date', Carbon::now()->month)
                ->whereNotNull('hours_worked')
                ->sum('hours_worked');
            $totalVolume = max(1, (int) ($teacher->volume_hours ?? 0));
            $contractProgress = [
                'worked_hours' => round($workedHoursMonth, 1),
                'total_hours' => (int) ($teacher->volume_hours ?? 0),
                'percentage' => $totalVolume > 0 ? min(100, round(($workedHoursMonth / $totalVolume) * 100, 1)) : 0,
            ];

            // KPI 2: Honoraires estimés (heures validées du mois × taux horaire)
            $estimatedHonoraria = round($workedHoursMonth * (float) ($teacher->hourly_rate ?? 0), 2);

            // KPI 3: Prochain cours du jour
            $nextCourse = null;
            $nowTime = Carbon::now()->format('H:i');
            $todaysCourses = Schedule::where('teacher_id', $teacher->id)
                ->with(['subject:id,name', 'classroom:id,name'])
                ->where('day_of_week', $todayDayName)
                ->where('start_time', '>=', $nowTime)
                ->orderBy('start_time')
                ->first();

            if (!$todaysCourses) {
                $todaysCourses = Schedule::where('teacher_id', $teacher->id)
                    ->with(['subject:id,name', 'classroom:id,name'])
                    ->where('day_of_week', $todayDayName)
                    ->orderBy('start_time')
                    ->first();
            }

            if ($todaysCourses) {
                $nextCourse = [
                    'subject_name' => $todaysCourses->subject->name ?? '—',
                    'classroom_name' => $todaysCourses->classroom->name ?? '—',
                    'start_time' => $todaysCourses->start_time,
                    'end_time' => $todaysCourses->end_time,
                    'day_of_week' => $todaysCourses->day_of_week,
                ];
            }

            return response()->json([
                'my_schedules' => $nextSchedules,
                'my_all_schedules' => Schedule::where('teacher_id', $teacher->id)->where('is_archived', false)->with(['subject', 'classroom'])->get(),
                'my_homeworks' => Homework::where('teacher_id', $teacher->id)->latest()->take(5)->get(),
                'my_attendance_month' => [
                    'present' => Attendance::where('user_id', $user->id)->whereMonth('attendance_date', now()->month)->where('status', 'present')->count(),
                    'absent' => Attendance::where('user_id', $user->id)->whereMonth('attendance_date', now()->month)->where('status', 'absent')->count(),
                    'late' => Attendance::where('user_id', $user->id)->whereMonth('attendance_date', now()->month)->where('status', 'late')->count(),
                    'total_days' => Attendance::where('user_id', $user->id)->whereMonth('attendance_date', now()->month)->count(),
                ],
                'attendance_this_week' => $weekAttendances->map(fn($a) => [
                    'date' => $a->attendance_date instanceof Carbon ? $a->attendance_date->format('Y-m-d') : $a->attendance_date,
                    'status' => $a->status,
                    'check_in' => $a->check_in_time ? substr($a->check_in_time, 0, 5) : null,
                    'check_out' => $a->check_out_time ? substr($a->check_out_time, 0, 5) : null,
                ]),
                'my_leave_balances' => [],
                'last_payslip' => Payroll::where('teacher_id', $teacher->id)->latest()->first(),
                'volume_hours_remaining' => $teacher->volume_hours,
                'pending_homeworks_count' => Homework::where('teacher_id', $teacher->id)->where('status', 'planned')->count(),
                'contract_progress' => $contractProgress,
                'estimated_honoraria' => $estimatedHonoraria,
                'next_course' => $nextCourse,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('TeacherStats error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des données'], 500);
        }
    }

    public function accountantStats(): JsonResponse {
        try {
            $payrollTrend = [];
            $grossTrend = [];
            for ($i = 5; $i >= 0; $i--) {
                $date = Carbon::today()->subMonths($i);
                $gross = Payroll::where('year', $date->year)->where('month', $date->month)->sum('gross_salary');
                $net = Payroll::where('year', $date->year)->where('month', $date->month)->sum('net_salary');
                $grossTrend[] = ['label' => $date->translatedFormat('M'), 'value' => $gross / 1000000];
                $payrollTrend[] = ['label' => $date->translatedFormat('M'), 'gross' => $gross / 1000000, 'net' => $net / 1000000];
            }

            $calculated = Payroll::where('year', now()->year)->where('month', now()->month)->where('status', 'calculated')->count();
            $validated = Payroll::where('year', now()->year)->where('month', now()->month)->where('status', 'validated')->count();
            $paid = Payroll::where('year', now()->year)->where('month', now()->month)->where('status', 'paid')->count();
            $totalNet = Payroll::where('month', now()->month)->where('year', now()->year)->sum('net_salary') ?: 0;
            $totalGross = Payroll::where('month', now()->month)->where('year', now()->year)->sum('gross_salary') ?: 0;
            $totalDeductions = Payroll::where('month', now()->month)->where('year', now()->year)->sum('deductions') ?: 0;

            $paymentMethods = Payroll::where('month', now()->month)->where('year', now()->year)
                ->selectRaw("payment_method, COUNT(*) as count, SUM(net_salary) as total")
                ->groupBy('payment_method')
                ->get();

            $prevMonth = Carbon::now()->subMonth();
            $prevNet = Payroll::where('year', $prevMonth->year)->where('month', $prevMonth->month)->sum('net_salary') ?: 0;
            $netVariation = $prevNet > 0 ? round((($totalNet - $prevNet) / $prevNet) * 100, 1) : 0;

            return response()->json([
                'payrolls_calculated' => $calculated,
                'payrolls_validated' => $validated,
                'payrolls_paid' => $paid,
                'payrolls_total' => $calculated + $validated + $paid,
                'total_net_salary' => (float)$totalNet,
                'total_gross_salary' => (float)$totalGross,
                'total_deductions' => (float)$totalDeductions,
                'net_variation' => $netVariation,
                'payment_methods' => $paymentMethods,
                'recent_payrolls' => Payroll::with('teacher.user')->latest()->take(10)->get(),
                'charts' => [
                    'payroll_gross' => $grossTrend,
                    'payroll_trend' => $payrollTrend,
                ]
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('AccountantStats error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des données'], 500);
        }
    }

    public function academicStats(): JsonResponse {
        try {
            $user = auth()->user();
            $isScoped = $user->hasRole('academic_manager') && !$user->hasRole('admin');
            $visibleUfrIds = $this->academicVisibleUfrIds($user);
            $scheduleScope = function ($query) use ($isScoped, $visibleUfrIds) {
                if ($isScoped) {
                    $query->whereHas('teacher', fn($teacher) => $teacher->whereIn('ufr_id', $visibleUfrIds));
                }
            };
            $homeworkScope = function ($query) use ($isScoped, $visibleUfrIds) {
                if ($isScoped) {
                    $query->whereHas('teacher', fn($teacher) => $teacher->whereIn('ufr_id', $visibleUfrIds));
                }
            };
            $dayNames = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
            $schedules = Schedule::whereIn('day_of_week', $dayNames)->where('is_archived', false);
            $scheduleScope($schedules);
            $schedulesThisWeek = $schedules->count();
            $homeworks = Homework::query();
            $homeworkScope($homeworks);
            $totalHomeworks = (clone $homeworks)->count();
            $pendingHomeworks = (clone $homeworks)->where('status', 'planned')->count();
            $submittedHomeworks = (clone $homeworks)->where('status', 'planned')->count();

            $ufrQuery = Ufr::with('teachers');
            if ($isScoped) $ufrQuery->whereIn('id', $visibleUfrIds);
            $teachersPerUfr = $ufrQuery->get()->map(function ($d) {
                return [
                    'name' => $d->name,
                    'teacher_count' => $d->teachers->count(),
                    'active_count' => $d->teachers->where('status', 'active')->count(),
                ];
            });

            $subjectQuery = Subject::withCount(['schedules' => $scheduleScope])
                ->orderBy('schedules_count', 'desc')->take(10);
            if ($isScoped) {
                $subjectQuery->whereHas('schedules.teacher', fn($teacher) => $teacher->whereIn('ufr_id', $visibleUfrIds));
            }
            $subjectDistribution = $subjectQuery->get();
            $recentSchedules = Schedule::with(['teacher.user', 'subject', 'classroom'])->latest();
            $scheduleScope($recentSchedules);

            return response()->json([
                'total_subjects' => $isScoped ? Subject::whereHas('schedules.teacher', fn($teacher) => $teacher->whereIn('ufr_id', $visibleUfrIds))->count() : Subject::count(),
                'total_classrooms' => Classroom::count(),
                'total_schedules' => (clone $schedules)->count(),
                'active_homeworks' => $pendingHomeworks,
                'submitted_homeworks' => $submittedHomeworks,
                'total_homeworks' => $totalHomeworks,
                'schedules_this_week' => $schedulesThisWeek,
                'teachers_per_ufr' => $teachersPerUfr,
                'recent_schedules' => $recentSchedules->take(8)->get(),
                'classroom_occupancy' => Classroom::withCount('schedules')->get(),
                'subject_distribution' => $subjectDistribution,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('AcademicStats error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur lors du chargement des données'], 500);
        }
    }
}
