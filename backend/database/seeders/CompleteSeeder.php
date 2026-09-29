<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\Teacher;
use App\Models\Ufr;
use App\Models\Subject;
use App\Models\Classroom;
use App\Models\LeaveType;
use App\Models\Schedule;
use App\Models\Attendance;
use App\Models\Homework;
use App\Models\Grade;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\LeaveRequest;
use App\Models\LeaveBalance;
use App\Models\Document;
use App\Models\Notification;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class CompleteSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Nettoyage de la base de données...');
        Schema::disableForeignKeyConstraints();

        User::truncate();
        Teacher::truncate();
        Ufr::truncate();
        Subject::truncate();
        Classroom::truncate();
        LeaveType::truncate();
        Schedule::truncate();
        Attendance::truncate();
        Homework::truncate();
        Grade::truncate();
        Payroll::truncate();
        PayrollDetail::truncate();
        LeaveRequest::truncate();
        LeaveBalance::truncate();
        Document::truncate();
        Notification::truncate();
        \DB::table('model_has_roles')->truncate();
        \DB::table('model_has_permissions')->truncate();
        \DB::table('role_has_permissions')->truncate();

        Schema::enableForeignKeyConstraints();

        $this->command->info('Création des rôles et permissions...');

        $adminPermissions = [
            'users.create', 'users.edit', 'users.delete', 'users.view_audit_logs',
            'ufrs.manage', 'subjects.manage', 'classrooms.manage',
            'attendance_codes.manage', 'payroll_settings.manage', 'leave_types.manage',
            'payroll.calculate', 'payroll.generate_payslip',
            'payroll.add_bonus', 'payroll.add_deduction', 'payroll.validate', 'payroll.export',
            'teachers.create', 'teachers.edit',
            'attendance.track', 'attendance.record_absence', 'attendance.validate_absence',
            'reports.generate',
            'schedules.create', 'schedules.assign_teachers', 'schedules.plan',
            'homeworks.validate', 'courses.create', 'exams.plan', 'ufrs.create',
            'schedules.view', 'courses.view', 'homeworks.publish',
            'attendance.mark_own', 'attendance.report_absence',
            'availability.declare', 'notifications.receive',
        ];

        $accountantPermissions = [
            'payroll.calculate', 'payroll.generate_payslip',
            'payroll.add_bonus', 'payroll.add_deduction', 'payroll.validate', 'payroll.export',
        ];

        $hrPermissions = [
            'teachers.create', 'teachers.edit',
            'attendance.track', 'attendance.record_absence', 'attendance.validate_absence',
            'reports.generate',
        ];

        $academicPermissions = [
            'schedules.create', 'homeworks.validate', 'schedules.assign_teachers',
            'courses.create', 'schedules.plan', 'exams.plan', 'ufrs.create',
        ];

        $teacherPermissions = [
            'schedules.view', 'courses.view', 'homeworks.publish',
            'attendance.report_absence', 'attendance.mark_own',
            'availability.declare', 'notifications.receive',
        ];

        $allPermissions = array_unique(array_merge(
            $adminPermissions, $accountantPermissions, $hrPermissions,
        ));

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $hrRole = Role::firstOrCreate(['name' => 'hr_manager']);
        $secretaryRole = Role::firstOrCreate(['name' => 'hr_manager']);
        $academicRole = Role::firstOrCreate(['name' => 'academic_manager']);
        $accountantRole = Role::firstOrCreate(['name' => 'accountant']);
        $teacherRole = Role::firstOrCreate(['name' => 'teacher']);

        $adminRole->syncPermissions($adminPermissions);
        $accountantRole->syncPermissions($accountantPermissions);
        $hrRole->syncPermissions($hrPermissions);
        $academicRole->syncPermissions($academicPermissions);
        $teacherRole->syncPermissions($teacherPermissions);

        $this->command->info('Création des UFR...');
        $ufrData = [
            ['name' => 'Génie Juridique', 'code' => 'UFR-GJ', 'description' => 'UFR de Génie Juridique'],
            ['name' => 'Génie Gestion Economique', 'code' => 'UFR-GGE', 'description' => 'UFR de Génie Gestion Economique'],
            ['name' => 'Sciences et Technologies', 'code' => 'UFR-ST', 'description' => 'UFR de Sciences et Technologies'],
            ['name' => 'Langues et Affaires', 'code' => 'UFR-LA', 'description' => 'UFR des Langues et Affaires'],
        ];
        $ufrIds = [];
        foreach ($ufrData as $u) {
            $ufrIds[] = Ufr::create($u)->id;
        }

        $this->command->info('Création des matières...');
        $subjects = [
            ['name' => 'Mathématiques', 'code' => 'MATH', 'level' => 'L1', 'credit_hours' => 3],
            ['name' => 'Physique', 'code' => 'PHY', 'level' => 'L1', 'credit_hours' => 3],
            ['name' => 'Chimie', 'code' => 'CHIM', 'level' => 'L1', 'credit_hours' => 2],
            ['name' => 'Français', 'code' => 'FR', 'level' => 'L1', 'credit_hours' => 2],
            ['name' => 'Littérature', 'code' => 'LIT', 'level' => 'L2', 'credit_hours' => 2],
            ['name' => 'Histoire', 'code' => 'HIST', 'level' => 'L1', 'credit_hours' => 2],
            ['name' => 'Géographie', 'code' => 'GEO', 'level' => 'L1', 'credit_hours' => 2],
            ['name' => 'Anglais', 'code' => 'ENG', 'level' => 'L2', 'credit_hours' => 2],
            ['name' => 'EPS', 'code' => 'SPORT', 'level' => 'L1', 'credit_hours' => 1],
        ];
        foreach ($subjects as $subject) {
            Subject::create($subject);
        }

        $this->command->info('Création des salles...');
        $classrooms = [
            ['name' => 'Salle A101', 'code' => 'A101', 'capacity' => 30, 'type' => 'classroom'],
            ['name' => 'Salle A102', 'code' => 'A102', 'capacity' => 25, 'type' => 'classroom'],
            ['name' => 'Salle B201', 'code' => 'B201', 'capacity' => 35, 'type' => 'classroom'],
            ['name' => 'Salle B202', 'code' => 'B202', 'capacity' => 40, 'type' => 'classroom'],
            ['name' => 'Labo Physique', 'code' => 'LP01', 'capacity' => 20, 'type' => 'lab'],
            ['name' => 'Labo Chimie', 'code' => 'LC01', 'capacity' => 20, 'type' => 'lab'],
            ['name' => 'Salle Informatique', 'code' => 'SI01', 'capacity' => 25, 'type' => 'lab'],
            ['name' => 'Gymnase', 'code' => 'GYM01', 'capacity' => 100, 'type' => 'amphitheater'],
        ];
        foreach ($classrooms as $classroom) {
            Classroom::create($classroom);
        }

        $this->command->info('Création des types de congés...');
        $leaveTypes = [
            ['name' => 'Congé Annuel', 'code' => 'AL', 'days_per_year' => 30],
            ['name' => 'Congé Maladie', 'code' => 'SL', 'days_per_year' => 10],
            ['name' => 'Congé Exceptionnel', 'code' => 'EL', 'days_per_year' => 5],
            ['name' => 'Congé Maternité', 'code' => 'ML', 'days_per_year' => 90],
            ['name' => 'Congé Formation', 'code' => 'FL', 'days_per_year' => 15],
        ];
        foreach ($leaveTypes as $leaveType) {
            LeaveType::create($leaveType);
        }

        $this->command->info('Création des utilisateurs...');
        $users = [
            [
                 'name' => 'Administrateur Système', 'email' => env('ADMIN_EMAIL', 'admin@sirh.local'),
                 'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
                'status' => 'active', 'role' => 'admin', 'teacher_data' => null,
                'must_change_password' => false,
            ],
            [
                'name' => 'Responsable Administratif', 'email' => 'rh@sirh.local',
                'password' => Hash::make(env('SEED_RH_PASSWORD', 'password')),
                'status' => 'active', 'role' => 'hr_manager', 'teacher_data' => null,
            ],
            [
                'name' => 'Chef UFR', 'email' => 'academic@sirh.local',
                'password' => Hash::make(env('SEED_ACADEMIC_PASSWORD', 'password')),
                'status' => 'active', 'role' => 'academic_manager', 'teacher_data' => null,
            ],
            [
                'name' => 'Comptable', 'email' => 'compta@sirh.local',
                'password' => Hash::make(env('SEED_COMPTA_PASSWORD', 'password')),
                'status' => 'active', 'role' => 'accountant', 'teacher_data' => null,
            ],
            [
                'name' => 'Professeur Martin', 'email' => 'prof@sirh.local',
                'password' => Hash::make(env('SEED_PROF_PASSWORD', 'password')),
                'status' => 'active', 'role' => 'teacher',
                'teacher_data' => ['employee_id' => 'EMP001', 'pin_code' => Hash::make('1001'), 'ufr_id' => $ufrIds[1] ?? 1, 'hire_date' => '2023-09-01', 'volume_hours' => 300, 'hourly_rate' => 5000, 'phone' => '771234567', 'address' => 'Dakar, Sénégal'],
            ],
            [
                'name' => 'Professeur Diop', 'email' => 'diop@sirh.local',
                'password' => Hash::make(env('SEED_PROF_PASSWORD', 'password')),
                'status' => 'active', 'role' => 'teacher',
                'teacher_data' => ['employee_id' => 'EMP002', 'pin_code' => Hash::make('1002'), 'ufr_id' => $ufrIds[2] ?? 2, 'hire_date' => '2022-09-01', 'volume_hours' => 350, 'hourly_rate' => 5000, 'phone' => '772345678', 'address' => 'Dakar, Sénégal'],
            ],
            [
                'name' => 'Professeur Sarr', 'email' => 'sarr@sirh.local',
                'password' => Hash::make(env('SEED_PROF_PASSWORD', 'password')),
                'status' => 'active', 'role' => 'teacher',
                'teacher_data' => ['employee_id' => 'EMP003', 'pin_code' => Hash::make('1003'), 'ufr_id' => $ufrIds[3] ?? 3, 'hire_date' => '2024-09-01', 'volume_hours' => 200, 'hourly_rate' => 5000, 'phone' => '773456789', 'address' => 'Dakar, Sénégal'],
            ],
        ];

        foreach ($users as $userData) {
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => $userData['password'],
                'status' => $userData['status'],
                'email_verified_at' => now(),
                'must_change_password' => $userData['must_change_password'] ?? true,
                'is_activated' => true,
                'activated_at' => now(),
            ]);

            $role = Role::where('name', $userData['role'])->first();
            $user->assignRole($role);

            if ($userData['role'] === 'teacher' && $userData['teacher_data']) {
                Teacher::create(array_merge($userData['teacher_data'], ['user_id' => $user->id]));
            }
        }

        // Le compte académique de démonstration dirige l'UFR Sciences et Technologies.
        $academicUser = User::where('email', 'academic@sirh.local')->first();
        if ($academicUser) {
            Ufr::where('code', 'UFR-ST')->update(['head_id' => $academicUser->id]);
        }

        $adminEmail = env('ADMIN_EMAIL', 'admin@sirh.local');
        $adminPw = env('ADMIN_PASSWORD', 'password');

        $this->command->info('✅ Base de données initialisée avec succès !');
        $this->command->info('');
        $this->command->info('📋 COMPTES CRÉÉS :');
        $this->command->info("├── Administrateur: $adminEmail (mot de passe: $adminPw)");
        $this->command->info('├── RH: rh@sirh.local');
        $this->command->info('├── Académique: academic@sirh.local');
        $this->command->info('├── Comptable: compta@sirh.local');
        $this->command->info('├── Prof Martin: prof@sirh.local');
        $this->command->info('├── Prof Diop: diop@sirh.local');
        $this->command->info('└── Prof Sarr: sarr@sirh.local');
    }
}
