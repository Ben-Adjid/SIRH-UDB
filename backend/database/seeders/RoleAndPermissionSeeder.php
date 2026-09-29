<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ─── PERMISSIONS PAR RÔLE ───

        // ADMIN (toutes les permissions)
        $adminPermissions = [
            'users.create', 'users.edit', 'users.delete', 'users.view_audit_logs',
            'departments.manage', 'subjects.manage', 'classrooms.manage',
            'attendance_codes.manage', 'payroll_settings.manage', 'leave_types.manage',
            'payroll.calculate', 'payroll.generate_payslip',
            'payroll.add_bonus', 'payroll.add_deduction', 'payroll.validate', 'payroll.export',
            'teachers.create', 'teachers.edit',
            'attendance.track', 'attendance.record_absence', 'attendance.validate_absence',
            'reports.generate',
            'schedules.create', 'schedules.assign_teachers', 'schedules.plan',
            'homeworks.validate', 'courses.create', 'exams.plan', 'departments.create',
            'schedules.view', 'courses.view', 'homeworks.publish',
            'attendance.mark_own', 'attendance.report_absence',
            'availability.declare', 'notifications.receive',
        ];

        // COMPTABLE
        $accountantPermissions = [
            'payroll.calculate',
            'payroll.generate_payslip',
            'payroll.add_bonus',
            'payroll.add_deduction',
            'payroll.validate',
            'payroll.export',
        ];

        // RESPONSABLE RH
        $hrPermissions = [
            'teachers.create',
            'teachers.edit',
            'attendance.track',
            'attendance.record_absence',
            'attendance.validate_absence',
            'reports.generate',
        ];

        // SECRÉTAIRE ADMINISTRATIF (mêmes accès que le Responsable Administratif)
        $secretaryPermissions = $hrPermissions;

        // CHEF DE DEPARTEMENT
        $academicPermissions = [
            'schedules.create',
            'homeworks.validate',
            'schedules.assign_teachers',
            'courses.create',
            'schedules.plan',
            'exams.plan',
            'departments.create',
        ];

        // ENSEIGNANT
        $teacherPermissions = [
            'schedules.view',
            'courses.view',
            'homeworks.publish',
            'attendance.report_absence',
            'attendance.mark_own',
            'attendance.track',
            'availability.declare',
            'notifications.receive',
        ];

        // Créer toutes les permissions uniques
        $allPermissions = array_unique(array_merge(
            $adminPermissions, $accountantPermissions, $hrPermissions,
            $secretaryPermissions, $academicPermissions, $teacherPermissions
        ));

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Créer les rôles (firstOrCreate pour éviter les doublons)
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $accountantRole = Role::firstOrCreate(['name' => 'accountant']);
        $hrRole = Role::firstOrCreate(['name' => 'hr_manager']);
        $secretaryRole = Role::firstOrCreate(['name' => 'hr_manager']);
        $academicRole = Role::firstOrCreate(['name' => 'academic_manager']);
        $teacherRole = Role::firstOrCreate(['name' => 'teacher']);

        // Sync les permissions (remplace les anciennes)
        $adminRole->syncPermissions($adminPermissions);
        $accountantRole->syncPermissions($accountantPermissions);
        $hrRole->syncPermissions($hrPermissions);
        $secretaryRole->syncPermissions($secretaryPermissions);
        $academicRole->syncPermissions($academicPermissions);
        $teacherRole->syncPermissions($teacherPermissions);
    }
}
