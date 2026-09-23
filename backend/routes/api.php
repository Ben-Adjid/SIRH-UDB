<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Api\AuthController;

// Health check - Simple status without sensitive info
Route::get('/health', fn() => response()->json(['status' => 'ok']))->middleware('throttle:60,1');

// Diagnostic routes (admin only)
Route::middleware('auth:sanctum', 'role:admin')->group(function () {
    Route::get('/diagnostic/database-counts', function () {
        return response()->json([
            'users' => \App\Models\User::count(),
            'users_active' => \App\Models\User::where('status', 'active')->count(),
            'teachers' => \App\Models\Teacher::count(),
            'teachers_active' => \App\Models\Teacher::where('status', 'active')->count(),
            'attendance' => \App\Models\Attendance::count(),
            'leaves' => \App\Models\LeaveRequest::count(),
            'schedules' => \App\Models\Schedule::count(),
            'homeworks' => \App\Models\Homework::count(),
            'payrolls' => \App\Models\Payroll::count(),
            'database_status' => 'OK',
        ]);
    });
});

// Auth
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\TwoFactorController;

Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/auth/register', [AuthController::class, 'register'])->middleware(['auth:sanctum', 'role:admin|hr_manager|secretary', 'throttle:15,1']);

// Refresh token
Route::middleware('auth:sanctum')->post('/auth/refresh', [AuthController::class, 'refreshToken'])->middleware('throttle:10,1');

// Routes publiques - Mot de passe oublié
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');

// Vérification d'email (publique - lien reçu par email, utilisateur non connecté)
Route::post('/auth/verify-email', [AuthController::class, 'verifyEmail'])->middleware('throttle:3,1');

// 2FA login verification (publique - utilisé après login si 2FA activé)
Route::post('/auth/2fa/verify-login', [TwoFactorController::class, 'verifyLogin'])->middleware('throttle:10,1');
Route::post('/auth/2fa/verify-recovery', [TwoFactorController::class, 'verifyWithRecoveryCode'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    // ── ROUTES COMMUNES (Tous les utilisateurs authentifiés) ──
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('throttle:30,1');
    Route::put('/auth/profile', [AuthController::class, 'updateProfile'])->middleware('throttle:20,1');
    Route::post('/auth/change-password', [AuthController::class, 'changePassword'])->middleware('throttle:5,1');
    Route::post('/auth/force-change-password', [AuthController::class, 'forceChangePassword'])->middleware('throttle:5,1');
    Route::post('/auth/send-verification-email', [AuthController::class, 'sendVerificationEmail'])->middleware('throttle:3,1');

    // ── DOUBLE AUTHENTIFICATION (2FA) ──
    Route::prefix('auth/2fa')->group(function () {
        Route::get('/secret', [TwoFactorController::class, 'getSecret']);
        Route::post('/enable', [TwoFactorController::class, 'enable'])->middleware('throttle:5,1');
        Route::post('/disable', [TwoFactorController::class, 'disable'])->middleware('throttle:5,1');
    });

    // ── VÉRIFICATION TÉLÉPHONE ──
    Route::prefix('auth/phone')->group(function () {
        Route::post('/send-verification', [TwoFactorController::class, 'sendPhoneVerification'])->middleware('throttle:3,1');
        Route::post('/verify', [TwoFactorController::class, 'verifyPhone'])->middleware('throttle:5,1');
    });

    // Notifications (tous)
    Route::get('/notifications', [NotificationController::class, 'index'])->middleware('throttle:60,1');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->middleware('throttle:30,1');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->middleware('throttle:60,1');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->middleware('throttle:30,1');

    // Dashboards
    Route::prefix('dashboards')->group(function () {
        Route::middleware('role:admin')->get('/admin', [App\Http\Controllers\Api\DashboardController::class, 'adminStats']);
        Route::middleware('role:admin|hr_manager|secretary')->get('/hr', [App\Http\Controllers\Api\DashboardController::class, 'hrStats']);
        Route::middleware('role:teacher')->get('/teacher', [App\Http\Controllers\Api\DashboardController::class, 'teacherStats']);
        Route::middleware('role:admin|academic_manager')->get('/academic', [App\Http\Controllers\Api\DashboardController::class, 'academicStats']);
        Route::middleware('role:admin|accountant')->get('/accountant', [App\Http\Controllers\Api\DashboardController::class, 'accountantStats']);
    });

    // ── ADMINISTRATION SYSTÈME (Admin uniquement) ──
    Route::middleware('role:admin|hr_manager|secretary')->group(function () {
        Route::get('/auth/audit-logs', [App\Http\Controllers\Api\UserController::class, 'auditLogs']);
        Route::delete('/auth/audit-logs', [App\Http\Controllers\Api\UserController::class, 'clearAuditLogs']);
        Route::post('/auth/users', [App\Http\Controllers\Api\UserController::class, 'store']);
    });
    Route::middleware('role:admin|hr_manager|secretary')->get('/auth/users', [App\Http\Controllers\Api\UserController::class, 'index']);
    Route::middleware('role:admin|hr_manager')->group(function () {
        Route::put('/auth/users/{user}', [App\Http\Controllers\Api\UserController::class, 'update']);
    });
    Route::middleware('role:admin')->group(function () {
        Route::delete('/auth/users/{user}', [App\Http\Controllers\Api\UserController::class, 'destroy']);
        Route::patch('/auth/users/{user}/toggle-status', [App\Http\Controllers\Api\UserController::class, 'toggleStatus']);
    });

    // ── DONNÉES DE RÉFÉRENCE (Lecture pour tous) ──
    Route::get('/academic-years', [App\Http\Controllers\Api\AcademicYearController::class, 'index'])->middleware('throttle:60,1');
    Route::middleware('role:admin|academic_manager')->group(function () {
        Route::post('/academic-years', [App\Http\Controllers\Api\AcademicYearController::class, 'store']);
        Route::put('/academic-years/{academicYear}', [App\Http\Controllers\Api\AcademicYearController::class, 'update']);
        Route::delete('/academic-years/{academicYear}', [App\Http\Controllers\Api\AcademicYearController::class, 'destroy']);
        Route::post('/academic-years/{academicYear}/activate', [App\Http\Controllers\Api\AcademicYearController::class, 'activate']);
        Route::post('/academic-years/{academicYear}/archive-semester', [App\Http\Controllers\Api\AcademicYearController::class, 'archiveSemester']);
        Route::post('/academic-years/{academicYear}/restore-semester', [App\Http\Controllers\Api\AcademicYearController::class, 'restoreSemester']);
        Route::delete('/academic-years/{academicYear}/delete-archived', [App\Http\Controllers\Api\AcademicYearController::class, 'deleteArchived']);
    });
    Route::get('/ufrs', [App\Http\Controllers\Api\UfrController::class, 'index'])->middleware('throttle:60,1');
    Route::get('/ufrs/{ufr}', [App\Http\Controllers\Api\UfrController::class, 'show'])->middleware('throttle:60,1');
    Route::get('/teaching-types', [App\Http\Controllers\Api\TeachingTypeController::class, 'index'])->middleware('throttle:60,1');
    Route::middleware('role:admin|academic_manager')->group(function () {
        Route::post('/teaching-types', [App\Http\Controllers\Api\TeachingTypeController::class, 'store']);
        Route::put('/teaching-types/{teachingType}', [App\Http\Controllers\Api\TeachingTypeController::class, 'update']);
        Route::delete('/teaching-types/{teachingType}', [App\Http\Controllers\Api\TeachingTypeController::class, 'destroy']);
    });
    Route::get('/teachers', [App\Http\Controllers\Api\TeacherController::class, 'index'])->middleware('throttle:60,1');
    Route::get('/teachers/{teacher}', [App\Http\Controllers\Api\TeacherController::class, 'show'])->middleware('throttle:60,1');
    Route::get('/subjects', [App\Http\Controllers\Api\SubjectController::class, 'index'])->middleware('throttle:60,1');
    Route::get('/subjects/{subject}', [App\Http\Controllers\Api\SubjectController::class, 'show'])->middleware('throttle:60,1');
    Route::get('/classrooms', [App\Http\Controllers\Api\ClassroomController::class, 'index'])->middleware('throttle:60,1');
    Route::get('/classrooms/{classroom}', [App\Http\Controllers\Api\ClassroomController::class, 'show'])->middleware('throttle:60,1');

    // Enseignants : création/modification (RH + Chef UFR, limité à son UFR côté contrôleur)
    Route::middleware('role:admin|hr_manager|secretary|academic_manager')->group(function () {
        Route::post('/teachers', [App\Http\Controllers\Api\TeacherController::class, 'store'])->middleware('throttle:30,1');
        Route::put('/teachers/{teacher}', [App\Http\Controllers\Api\TeacherController::class, 'update'])->middleware('throttle:60,1');
    });

    // ── RESSOURCES HUMAINES (Admin + RH) ──
    Route::middleware('role:admin|hr_manager|secretary')->group(function () {
        // Enseignants
        Route::delete('/teachers/{teacher}', [App\Http\Controllers\Api\TeacherController::class, 'destroy'])->middleware('throttle:30,1');
        Route::patch('/teachers/{teacher}/archive', [App\Http\Controllers\Api\TeacherController::class, 'archive']);

        // Contrats
        Route::post('/teachers/{teacher}/contracts', [App\Http\Controllers\Api\TeacherController::class, 'storeContract'])->middleware('throttle:30,1');
        Route::put('/teachers/{teacher}/contract', [App\Http\Controllers\Api\TeacherController::class, 'updateContract'])->middleware('throttle:60,1');

        // Rapports
        Route::get('/reports/teachers', [App\Http\Controllers\Api\TeacherController::class, 'report']);
    });

    Route::middleware('role:admin|hr_manager|secretary|academic_manager')->group(function () {
        Route::get('/reports/attendance', [App\Http\Controllers\Api\AttendanceController::class, 'report']);
    });

    // Documents enseignants (teacher accède aux siens, admin/rh accède à tous)
    Route::middleware('role:admin|hr_manager|secretary|accountant|teacher')->group(function () {
        Route::get('/teachers/{teacher}/documents', [DocumentController::class, 'index'])->middleware('throttle:120,1');
        Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->middleware('throttle:30,1');
    });
    Route::middleware('role:admin|hr_manager|secretary|accountant|teacher')->group(function () {
        Route::post('/teachers/{teacher}/documents', [DocumentController::class, 'store'])->middleware('throttle:20,1');
        Route::put('/documents/{document}', [DocumentController::class, 'update'])->middleware('throttle:20,1');
        Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->middleware('throttle:20,1');
    });

    // ── UFR (Admin + Chef UFR) ──
    Route::middleware('role:admin|academic_manager')->group(function () {
        Route::post('/ufrs', [App\Http\Controllers\Api\UfrController::class, 'store']);
        Route::put('/ufrs/{ufr}', [App\Http\Controllers\Api\UfrController::class, 'update']);
        Route::delete('/ufrs/{ufr}', [App\Http\Controllers\Api\UfrController::class, 'destroy']);
    });

    // ── ACADÉMIQUE (Admin + Chef UFR) ──
    Route::middleware('role:admin|academic_manager')->group(function () {
        Route::post('/subjects', [App\Http\Controllers\Api\SubjectController::class, 'store']);
        Route::put('/subjects/{subject}', [App\Http\Controllers\Api\SubjectController::class, 'update']);
        Route::delete('/subjects/{subject}', [App\Http\Controllers\Api\SubjectController::class, 'destroy']);

        Route::post('/classrooms', [App\Http\Controllers\Api\ClassroomController::class, 'store']);
        Route::put('/classrooms/{classroom}', [App\Http\Controllers\Api\ClassroomController::class, 'update']);
        Route::delete('/classrooms/{classroom}', [App\Http\Controllers\Api\ClassroomController::class, 'destroy']);

        Route::patch('/homeworks/{homework}/approve', [App\Http\Controllers\Api\HomeworkController::class, 'approve']);
        Route::patch('/homeworks/{homework}/complete', [App\Http\Controllers\Api\HomeworkController::class, 'complete']);
        Route::patch('/homeworks/{homework}/cancel', [App\Http\Controllers\Api\HomeworkController::class, 'cancel']);
        Route::patch('/homeworks/{homework}/reschedule', [App\Http\Controllers\Api\HomeworkController::class, 'reschedule']);

        // Examens
        Route::post('/exams', [App\Http\Controllers\Api\ScheduleController::class, 'storeExam']);
        Route::put('/exams/{schedule}', [App\Http\Controllers\Api\ScheduleController::class, 'updateExam']);
        Route::delete('/exams/{schedule}', [App\Http\Controllers\Api\ScheduleController::class, 'destroyExam']);
    });

    Route::middleware('role:admin|academic_manager|teacher|hr_manager|secretary')->get('/exams', [App\Http\Controllers\Api\ScheduleController::class, 'exams']);


    // ── POINTAGE ──
    // Enseignant: pointer entrée/sortie, signaler absence
    Route::post('/attendance/check-in', [App\Http\Controllers\Api\AttendanceController::class, 'checkIn'])->middleware('throttle:10,1');
    Route::post('/attendance/check-out', [App\Http\Controllers\Api\AttendanceController::class, 'checkOut'])->middleware('throttle:10,1');
    Route::post('/attendance/check-in-online', [App\Http\Controllers\Api\AttendanceController::class, 'checkInOnline'])->middleware('throttle:10,1');
    Route::post('/attendance/check-out-online', [App\Http\Controllers\Api\AttendanceController::class, 'checkOutOnline'])->middleware('throttle:10,1');
    Route::get('/attendance/my-pin', [App\Http\Controllers\Api\AttendanceController::class, 'myPin'])->middleware('throttle:60,1');
    Route::post('/attendance/generate-pin', [App\Http\Controllers\Api\AttendanceController::class, 'generateTemporaryPin'])->middleware('throttle:10,1');
    Route::get('/attendance/active-code', [App\Http\Controllers\Api\AttendanceController::class, 'getActiveCode'])->middleware('throttle:30,1');
    Route::get('/attendance', [App\Http\Controllers\Api\AttendanceController::class, 'index'])->middleware('throttle:60,1');
    Route::post('/attendance/report-absence', [App\Http\Controllers\Api\AttendanceController::class, 'reportAbsence'])->middleware('throttle:10,1');
    Route::post('/attendance/declare-availability', [App\Http\Controllers\Api\AttendanceController::class, 'declareAvailability'])->middleware('throttle:10,1');
    Route::get('/attendance/weekly', [App\Http\Controllers\Api\AttendanceController::class, 'weekly'])->middleware('throttle:30,1');

    Route::middleware('role:admin|hr_manager|secretary|accountant|academic_manager')->group(function () {
        Route::get('/attendance/stats', [App\Http\Controllers\Api\AttendanceController::class, 'stats'])->middleware('throttle:30,1');
         Route::get('/attendance/export', [App\Http\Controllers\Api\AttendanceController::class, 'export'])->middleware('throttle:30,1');
        Route::get('/availabilities', [App\Http\Controllers\Api\AttendanceController::class, 'getAvailabilities'])->middleware('throttle:30,1');
    });

    // Admin/RH/Chef UFR: gestion des présences/absences
    Route::middleware('role:admin|hr_manager|secretary|academic_manager')->group(function () {
        Route::post('/attendance/generate-code', [App\Http\Controllers\Api\AttendanceController::class, 'generateCode']);
        Route::patch('/attendance/{attendance}/validate-absence', [App\Http\Controllers\Api\AttendanceController::class, 'validateAbsence']);
    });
    Route::middleware('role:admin|hr_manager|secretary')->group(function () {
        Route::patch('/attendance/{attendance}', [App\Http\Controllers\Api\AttendanceController::class, 'update']);
        Route::post('/attendance/record-absence', [App\Http\Controllers\Api\AttendanceController::class, 'recordAbsence']);
    });

    // ── CONGÉS (réservés au personnel non enseignant) ──
    Route::middleware('role:admin|hr_manager|secretary|academic_manager|accountant')->group(function () {
        Route::post('/leaves', [App\Http\Controllers\Api\LeaveController::class, 'store'])->middleware('throttle:30,1');
        Route::get('/leaves/history', [App\Http\Controllers\Api\LeaveController::class, 'getHistory'])->middleware('throttle:60,1');
        Route::get('/leaves/balances', [App\Http\Controllers\Api\LeaveController::class, 'getBalances'])->middleware('throttle:60,1');
    });
    Route::middleware('role:admin|hr_manager|secretary|academic_manager|accountant')->group(function () {
        Route::get('/leave-types', [App\Http\Controllers\Api\LeaveController::class, 'getLeaveTypes'])->middleware('throttle:60,1');
    });

    Route::middleware('role:admin|hr_manager|secretary|academic_manager|accountant')->group(function () {
        Route::get('/leaves', [App\Http\Controllers\Api\LeaveController::class, 'index']);
    });
    Route::middleware('role:admin|hr_manager|secretary')->group(function () {
        Route::patch('/leaves/{leave}/status', [App\Http\Controllers\Api\LeaveController::class, 'updateStatus'])->middleware('throttle:60,1');
        Route::post('/leaves/update-balance', [App\Http\Controllers\Api\LeaveController::class, 'updateBalance'])->middleware('throttle:30,1');
    });

    // Emploi du temps et devoirs
    Route::middleware('role:admin|academic_manager|teacher|hr_manager|secretary')->group(function () {
        Route::apiResource('schedules', App\Http\Controllers\Api\ScheduleController::class)->middleware('throttle:60,1');
        Route::apiResource('homeworks', App\Http\Controllers\Api\HomeworkController::class)->middleware('throttle:60,1');
        Route::post('/homeworks/{homework}/grades', [App\Http\Controllers\Api\HomeworkController::class, 'recordGrades'])->middleware('throttle:30,1');
        Route::get('/homeworks/{homework}/grades', [App\Http\Controllers\Api\HomeworkController::class, 'getGrades'])->middleware('throttle:30,1');
    });

    // ── PAIE (Admin + Comptable + RH) ──
    Route::middleware('role:admin|accountant|hr_manager|secretary')->group(function () {
        Route::get('/payroll/attendance-hours', [App\Http\Controllers\Api\PayrollController::class, 'attendanceSummary']);
        Route::post('/payroll/calculate', [App\Http\Controllers\Api\PayrollController::class, 'calculate'])->middleware('throttle:30,1');
        Route::post('/payroll/calculate-month', [App\Http\Controllers\Api\PayrollController::class, 'calculateMonth'])->middleware('throttle:30,1');
        Route::post('/payroll/{payroll}/line-item', [App\Http\Controllers\Api\PayrollController::class, 'addLineItem'])->middleware('throttle:30,1');
        Route::post('/payroll/{payroll}/validate', [App\Http\Controllers\Api\PayrollController::class, 'validatePayroll'])->middleware('throttle:30,1');
        Route::post('/payroll/{payroll}/mark-as-paid', [App\Http\Controllers\Api\PayrollController::class, 'markAsPaid'])->middleware('throttle:30,1');
        Route::get('/payroll/export', [App\Http\Controllers\Api\PayrollController::class, 'export']);
    });

    // Consultation paie (admin, RH, comptable, ou enseignant pour son propre)
    Route::middleware('role:admin|hr_manager|secretary|accountant|teacher')->group(function () {
        Route::get('/payroll', [App\Http\Controllers\Api\PayrollController::class, 'index']);
        Route::get('/payroll/{payroll}', [App\Http\Controllers\Api\PayrollController::class, 'show']);
        Route::get('/payroll/{payroll}/pdf', [App\Http\Controllers\Api\PayrollController::class, 'downloadPDF']);
    });

    // Disponibilités enseignants (now in the attendance group above)
});
