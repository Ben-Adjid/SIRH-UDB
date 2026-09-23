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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
            $cacheKey = 'dashboard:admin:' . auth()->id();

            $cached = Cache::get($cacheKey);
            if ($cached) {
                Log::debug('[Dashboard] Using cached admin stats');
                return response()->json($cached);
            }

            $today = Carbon::today()->toDateString();

            $attendanceByDateRaw = Attendance::select('attendance_date')
                ->selectRaw("COUNT(*) as total, SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present, SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late")
                ->whereDate('attendance_date', '>=', Carbon::today()->subDays(6)->toDateString())
                ->groupBy('attendance_date')
                ->get();

            $attendanceByDate = $attendanceByDateRaw->keyBy('attendance_date');

            $attendanceTrend = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::today()->subDays($i);
                $data = isset($attendanceByDate[$date->toDateString()]) ? $attendanceByDate[$date->toDateString()] : (object)['total' => 0, 'present' => 0, 'late' => 0];
                $attendanceTrend[] = [
                    'day' => $date->translatedFormat('D'),
                    'total' => (int)($data->total ?? 0),
                    'present' => (int)($data->present ?? 0),
                    'late' => (int)($data->late ?? 0),
                ];
            }

            $payrollByMonthRaw = Payroll::selectRaw("CONCAT(year, '-', month) as period, SUM(net_salary) as total")
                ->where('status', 'paid')
                ->where('year', '>=', Carbon::today()->subMonths(5)->year)
                ->groupBy('year', 'month')
                ->get();

            $payrollByMonth = $payrollByMonthRaw->keyBy('period');

            $payrollTrend = [];
            for ($i = 5; $i >= 0; $i--) {
                $date = Carbon::today()->subMonths($i);
                $key = $date->year . '-' . $date->month;
                $data = $payrollByMonth[$key] ?? (object)['total' => 0];
                $total = isset($data->total) ? (float)$data->total : 0;
                $payrollTrend[] = [
                    'month' => $date->translatedFormat('M'),
                    'amount' => (float)$total
                ];
            }

            $todayStats = Attendance::whereDate('attendance_date', $today)
                ->selectRaw("SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present, SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late, SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent, COUNT(*) as total")
                ->first();

            $todayPresent = (int)($todayStats->present ?? 0);
            $todayLate = (int)($todayStats->late ?? 0);
            $todayAbsent = (int)($todayStats->absent ?? 0);
            $todayTotal = (int)($todayStats->total ?? 0);
            $attendanceRate = $todayTotal > 0 ? round((($todayPresent + $todayLate) / $todayTotal) * 100, 1) : 0;

            $monthPayroll = Payroll::where('year', now()->year)->where('month', now()->month)
                ->selectRaw("SUM(net_salary) as net, SUM(gross_salary) as gross")
                ->first();
            $monthNet = (float)($monthPayroll->net ?? 0);
            $monthGross = (float)($monthPayroll->gross ?? 0);

            // Optimized counting with single queries
            $userStats = User::selectRaw("COUNT(*) as total, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active")
                ->first();
            $activeUsers = $userStats->active ?? 0;
            $totalUsers = $userStats->total ?? 0;

            $monthAttendanceStats = Attendance::whereYear('attendance_date', now()->year)
                ->whereMonth('attendance_date', now()->month)
                ->selectRaw("COUNT(*) as total, SUM(CASE WHEN status IN ('absent', 'late') THEN 1 ELSE 0 END) as absences")
                ->first();
            $monthAbsences = $monthAttendanceStats->absences ?? 0;
            $monthTotalAttendance = $monthAttendanceStats->total ?? 0;
            $todayByStatus = Attendance::whereDate('attendance_date', $today)
                ->selectRaw("status, COUNT(*) as count")
                ->groupBy('status')
                ->pluck('count', 'status');

            // Optimized teacher counting
            $teacherStats = Teacher::selectRaw("COUNT(*) as total, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active, SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive")
                ->first();

            Log::info('[Dashboard] Admin stats loaded', [
                'total_users' => $totalUsers,
                'total_teachers' => (int)($teacherStats->total ?? 0),
                'timestamp' => now(),
            ]);

            $data = [
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'inactive_users' => $totalUsers - $activeUsers,
                'total_teachers' => (int)($teacherStats->total ?? 0),
                'active_teachers' => (int)($teacherStats->active ?? 0),
                'teachers_on_leave' => 0,
                'teachers_inactive' => (int)($teacherStats->inactive ?? 0),
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
                'month_net_payroll' => $monthNet,
                'month_gross_payroll' => $monthGross,
                'honoraires' => Teacher::selectRaw("COALESCE(hourly_rate, 0) as hourly_rate, COUNT(*) as count")
                    ->groupBy('hourly_rate')->get()->pluck('count', 'hourly_rate'),
                'recent_audits' => AuditLog::with('user:id,name')->latest()->take(8)->get(['id', 'action', 'model', 'model_id', 'user_id', 'created_at']),
                'recent_notifications' => \App\Models\Notification::where('user_id', auth()->id())->latest()->take(5)->get(),
                'payroll_status' => (object)Payroll::where('year', now()->year)->where('month', now()->month)->pluck('status', 'teacher_id')->toArray(),
                'recent_teachers' => Teacher::with(['user:id,name,email,status'])->latest()->take(6)->get(['id', 'user_id', 'employee_id', 'hourly_rate', 'status', 'created_at']),
                'charts' => [
                    'attendance' => $attendanceTrend,
                    'payroll' => $payrollTrend,
                ]
            ];

            Cache::put($cacheKey, $data, now()->addSeconds(config('cache_durations.dashboard', 120)));
            return response()->json($data);
        } catch (\Throwable $e) {
            Log::error('[Dashboard] AdminStats error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'timestamp' => now(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Erreur lors du chargement des données'], 500);
        }
    }

    public function hrStats(): JsonResponse {
        try {
            $cacheKey = 'dashboard:hr:' . auth()->id();

            $cached = Cache::get($cacheKey);
            if ($cached) {
                Log::debug('[Dashboard] Using cached HR stats');
                return response()->json($cached);
            }

            $today = Carbon::today()->toDateString();
            $startOfMonth = Carbon::now()->startOfMonth()->toDateString();

            $absenceByDateRaw = Attendance::select('attendance_date')
                ->selectRaw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent, SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late")
                ->whereDate('attendance_date', '>=', Carbon::today()->subDays(6)->toDateString())
                ->groupBy('attendance_date')
                ->get();

            $absenceByDate = $absenceByDateRaw->keyBy('attendance_date');

            $absenceTrend = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::today()->subDays($i);
                $data = isset($absenceByDate[$date->toDateString()]) ? $absenceByDate[$date->toDateString()] : (object)['absent' => 0, 'late' => 0];
                $absenceTrend[] = [
                    'label' => $date->translatedFormat('D'),
                    'absent' => (int)($data->absent ?? 0),
                    'late' => (int)($data->late ?? 0),
                    'total' => (int)(($data->absent ?? 0) + ($data->late ?? 0)),
                ];
            }

            $pendingRequests = LeaveRequest::where('status', 'pending')
                ->with(['user', 'teacher.user', 'leaveType'])
                ->latest()
                ->take(10)
                ->get();

            $todayStats = Attendance::whereDate('attendance_date', $today)
                ->selectRaw("SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present, SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late, SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent, COUNT(*) as total")
                ->first();

            $todayPresent = (int)($todayStats->present ?? 0);
            $todayLate = (int)($todayStats->late ?? 0);
            $todayAbsent = (int)($todayStats->absent ?? 0);
            $todayTotal = (int)($todayStats->total ?? 0);

            $monthStats = Attendance::where('attendance_date', '>=', $startOfMonth)
                ->selectRaw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent, SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late, COUNT(*) as total")
                ->first();

            $monthAbsent = (int)($monthStats->absent ?? 0);
            $monthLate = (int)($monthStats->late ?? 0);
            $monthTotal = (int)($monthStats->total ?? 0);
            $monthRate = $monthTotal > 0 ? round((($monthTotal - $monthAbsent - $monthLate) / $monthTotal) * 100, 1) : 0;

            $teacherStats = Teacher::selectRaw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active, COUNT(*) as total")
                ->first();

            Log::info('[Dashboard] HR stats loaded', [
                'teachers_active' => (int)($teacherStats->active ?? 0),
                'teachers_total' => (int)($teacherStats->total ?? 0),
                'timestamp' => now(),
            ]);

            $data = [
                'teachers_active' => (int)($teacherStats->active ?? 0),
                'teachers_total' => (int)($teacherStats->total ?? 0),
                'pending_leave_requests' => $pendingRequests,
                'pending_leave_count' => LeaveRequest::where('status', 'pending')->count(),
                'attendance_today' => $todayTotal,
                'today_present' => $todayPresent,
                'today_late' => $todayLate,
                'today_absent' => $todayAbsent,
                'month_attendance_rate' => $monthRate,
                'honoraires' => Teacher::selectRaw('hourly_rate, COUNT(*) as count')->whereNotNull('hourly_rate')->groupBy('hourly_rate')->get(),
                'ufr_distribution' => Ufr::withCount('teachers')->get(),
                'recent_attendances' => Attendance::with(['teacher:id,user_id', 'teacher.user:id,name,email', 'user:id,name'])
                    ->where('attendance_date', $today)
                    ->latest()
                    ->take(10)
                    ->get(['id', 'teacher_id', 'user_id', 'attendance_date', 'status', 'check_in_time', 'check_out_time']),
                'charts' => [
                    'absences' => $absenceTrend
                ]
            ];

            Cache::put($cacheKey, $data, now()->addSeconds(config('cache_durations.dashboard', 120)));
            return response()->json($data);
        } catch (\Throwable $e) {
            Log::error('[Dashboard] HrStats error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'timestamp' => now(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Erreur lors du chargement des données'], 500);
        }
    }

    public function teacherStats(): JsonResponse {
        try {
            $cacheKey = 'dashboard:teacher:' . auth()->id();

            $cached = Cache::get($cacheKey);
            if ($cached) {
                Log::debug('[Dashboard] Using cached teacher stats');
                return response()->json($cached);
            }

            $user = auth()->user();
            $teacher = Teacher::where('user_id', $user->id)->with('user:id,name,email')->first();

            if (!$teacher) {
                Log::warning('[Dashboard] Teacher not found for user', ['user_id' => $user->id]);
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

            $attendanceMonth = Attendance::where('user_id', $user->id)
                ->whereMonth('attendance_date', now()->month)
                ->selectRaw("SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present, SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent, SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late, COUNT(*) as total")
                ->first();

            Log::info('[Dashboard] Teacher stats loaded', [
                'teacher_id' => $teacher->id,
                'user_id' => $user->id,
                'timestamp' => now(),
            ]);

            $data = [
                'my_schedules' => $nextSchedules,
                'my_all_schedules' => Schedule::where('teacher_id', $teacher->id)->where('is_archived', false)->with(['subject:id,name', 'classroom:id,name'])->get(['id', 'teacher_id', 'subject_id', 'classroom_id', 'day_of_week', 'start_time', 'end_time', 'is_archived']),
                'my_homeworks' => Homework::where('teacher_id', $teacher->id)->latest()->take(5)->get(['id', 'teacher_id', 'title', 'status', 'scheduled_date', 'created_at']),
                'my_attendance_month' => [
                    'present' => (int)($attendanceMonth->present ?? 0),
                    'absent' => (int)($attendanceMonth->absent ?? 0),
                    'late' => (int)($attendanceMonth->late ?? 0),
                    'total_days' => (int)($attendanceMonth->total ?? 0),
                ],
                'attendance_this_week' => $weekAttendances->map(fn($a) => [
                    'date' => $a->attendance_date instanceof Carbon ? $a->attendance_date->format('Y-m-d') : $a->attendance_date,
                    'status' => $a->status,
                    'check_in' => $a->check_in_time ? substr($a->check_in_time, 0, 5) : null,
                    'check_out' => $a->check_out_time ? substr($a->check_out_time, 0, 5) : null,
                ]),
                'my_leave_balances' => [],
                'last_payslip' => Payroll::where('teacher_id', $teacher->id)->latest()->first(['id', 'teacher_id', 'year', 'month', 'net_salary', 'gross_salary', 'status']),
                'volume_hours_remaining' => $teacher->volume_hours,
                'pending_homeworks_count' => Homework::where('teacher_id', $teacher->id)->where('status', 'planned')->count(),
                'contract_progress' => $contractProgress,
                'estimated_honoraria' => $estimatedHonoraria,
                'next_course' => $nextCourse,
            ];

            Cache::put($cacheKey, $data, now()->addSeconds(config('cache_durations.dashboard', 120)));
            return response()->json($data);
        } catch (\Throwable $e) {
            Log::error('[Dashboard] TeacherStats error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'timestamp' => now(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Erreur lors du chargement des données'], 500);
        }
    }

    public function accountantStats(): JsonResponse {
        try {
            $cacheKey = 'dashboard:accountant:' . auth()->id();

            $cached = Cache::get($cacheKey);
            if ($cached) {
                Log::debug('[Dashboard] Using cached accountant stats');
                return response()->json($cached);
            }

            $payrollByMonth = Payroll::selectRaw("CONCAT(year, '-', month) as period, SUM(CASE WHEN status = 'paid' THEN gross_salary ELSE 0 END) as gross, SUM(CASE WHEN status = 'paid' THEN net_salary ELSE 0 END) as net")
                ->where('year', '>=', Carbon::today()->subMonths(5)->year)
                ->groupBy('year', 'month')
                ->get()
                ->keyBy('period');

            $payrollTrend = [];
            $grossTrend = [];
            for ($i = 5; $i >= 0; $i--) {
                $date = Carbon::today()->subMonths($i);
                $key = $date->year . '-' . $date->month;
                $data = $payrollByMonth[$key] ?? (object)['gross' => 0, 'net' => 0];
                $grossTrend[] = ['label' => $date->translatedFormat('M'), 'value' => (float)($data->gross ?? 0) / 1000000];
                $payrollTrend[] = ['label' => $date->translatedFormat('M'), 'gross' => (float)($data->gross ?? 0) / 1000000, 'net' => (float)($data->net ?? 0) / 1000000];
            }

            $currentMonthStats = Payroll::where('year', now()->year)->where('month', now()->month)
                ->selectRaw("SUM(CASE WHEN status = 'calculated' THEN 1 ELSE 0 END) as calculated, SUM(CASE WHEN status = 'validated' THEN 1 ELSE 0 END) as validated, SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid, SUM(net_salary) as net, SUM(gross_salary) as gross, SUM(deductions) as deductions, COUNT(*) as total")
                ->first();

            $calculated = (int)($currentMonthStats->calculated ?? 0);
            $validated = (int)($currentMonthStats->validated ?? 0);
            $paid = (int)($currentMonthStats->paid ?? 0);
            $totalNet = (float)($currentMonthStats->net ?? 0);
            $totalGross = (float)($currentMonthStats->gross ?? 0);
            $totalDeductions = (float)($currentMonthStats->deductions ?? 0);

            $paymentMethods = Payroll::where('month', now()->month)->where('year', now()->year)
                ->selectRaw("payment_method, COUNT(*) as count, SUM(net_salary) as total")
                ->groupBy('payment_method')
                ->get();

            $prevMonth = Carbon::now()->subMonth();
            $prevMonthStats = Payroll::where('year', $prevMonth->year)->where('month', $prevMonth->month)
                ->selectRaw("SUM(net_salary) as net")
                ->first();
            $prevNet = (float)($prevMonthStats->net ?? 0);
            $netVariation = $prevNet > 0 ? round((($totalNet - $prevNet) / $prevNet) * 100, 1) : 0;

            Log::info('[Dashboard] Accountant stats loaded', [
                'payrolls_paid' => $paid,
                'total_net' => $totalNet,
                'timestamp' => now(),
            ]);

            $data = [
                'payrolls_calculated' => $calculated,
                'payrolls_validated' => $validated,
                'payrolls_paid' => $paid,
                'payrolls_total' => $calculated + $validated + $paid,
                'total_net_salary' => $totalNet,
                'total_gross_salary' => $totalGross,
                'total_deductions' => $totalDeductions,
                'net_variation' => $netVariation,
                'payment_methods' => $paymentMethods,
                'recent_payrolls' => Payroll::with(['teacher:id,user_id', 'teacher.user:id,name,email'])->latest()->take(10)->get(['id', 'teacher_id', 'year', 'month', 'net_salary', 'gross_salary', 'status', 'created_at']),
                'charts' => [
                    'payroll_gross' => $grossTrend,
                    'payroll_trend' => $payrollTrend,
                ]
            ];

            Cache::put($cacheKey, $data, now()->addSeconds(config('cache_durations.dashboard', 120)));
            return response()->json($data);
        } catch (\Throwable $e) {
            Log::error('[Dashboard] AccountantStats error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'timestamp' => now(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Erreur lors du chargement des données'], 500);
        }
    }

    public function academicStats(): JsonResponse {
        try {
            $cacheKey = 'dashboard:academic:' . auth()->id();

            $cached = Cache::get($cacheKey);
            if ($cached) {
                Log::debug('[Dashboard] Using cached academic stats');
                return response()->json($cached);
            }

            $user = auth()->user();
            $isScoped = !$user->hasRole('admin') && $user->hasRole('academic_manager');
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
            $submittedHomeworks = (clone $homeworks)->where('status', 'completed')->count();

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

            $recentSchedules = Schedule::with(['teacher:id,user_id,ufr_id', 'teacher.user:id,name,email', 'subject:id,name', 'classroom:id,name'])
                ->latest()
                ->take(8);
            $scheduleScope($recentSchedules);

            $totalSubjects = $isScoped
                ? Subject::where(fn($q) => $q->whereIn('ufr_id', $visibleUfrIds)
                    ->orWhereHas('schedules.teacher', fn($teacher) => $teacher->whereIn('ufr_id', $visibleUfrIds)))->count()
                : Subject::count();

            Log::info('[Dashboard] Academic stats loaded', [
                'total_subjects' => $totalSubjects,
                'user_role' => auth()->user()->roles->pluck('name')->first(),
                'is_scoped' => $isScoped,
                'timestamp' => now(),
            ]);

            $data = [
                'total_subjects' => $totalSubjects,
                'total_classrooms' => Classroom::count(),
                'total_schedules' => (clone $schedules)->count(),
                'active_homeworks' => $pendingHomeworks,
                'submitted_homeworks' => $submittedHomeworks,
                'total_homeworks' => $totalHomeworks,
                'schedules_this_week' => $schedulesThisWeek,
                'teachers_per_ufr' => $teachersPerUfr,
                'recent_schedules' => $recentSchedules->get(['id', 'teacher_id', 'subject_id', 'classroom_id', 'day_of_week', 'start_time', 'end_time', 'is_archived', 'created_at']),
                'classroom_occupancy' => Classroom::withCount('schedules')->get(['id', 'name']),
                'subject_distribution' => $subjectDistribution,
            ];

            Cache::put($cacheKey, $data, now()->addMinutes(2));
            return response()->json($data);
        } catch (\Throwable $e) {
            Log::error('[Dashboard] AcademicStats error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'timestamp' => now(),
            ]);
            return response()->json(['message' => 'Erreur lors du chargement des données'], 500);
        }
    }
}
