<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Teacher;
use App\Models\Ufr;
use App\Models\Subject;
use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\Attendance;
use App\Models\LeaveType;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ComprehensiveTestDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RoleAndPermissionSeeder::class]);
        $this->call([UfrAndTeachingTypeSeeder::class]);

        DB::transaction(function () {
            $this->seedAcademicStructure();
            $this->seedActors();
            $this->seedSchedules();
            $this->seedAttendance();
            $this->seedLeaves();
            $this->seedPayrolls();
        });
        echo "\n✅ Données de test complètes créées avec succès!\n";
    }

    private function seedActors(): void
    {
        // Récupérer les UFRs déjà créés
        $ufrs = Ufr::all();
        if ($ufrs->isEmpty()) {
            throw new \Exception('Les UFRs doivent être créées avant les acteurs. Appelez seedAcademicStructure() d\'abord.');
        }

        // 1. ADMIN
        $admin = User::create([
            'name' => 'Administrateur SIRH',
            'email' => 'admin@sirh.local',
            'phone' => '+221771234567',
            'password' => Hash::make('Admin@2026'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        // 2. HR MANAGER
        $hr = User::create([
            'name' => 'Responsable RH',
            'email' => 'hr@sirh.local',
            'phone' => '+221771234568',
            'password' => Hash::make('HR@2026'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $hr->assignRole('hr_manager');

        // 3. ACCOUNTANT
        $accountant = User::create([
            'name' => 'Comptable Paie',
            'email' => 'accountant@sirh.local',
            'phone' => '+221771234569',
            'password' => Hash::make('Accountant@2026'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $accountant->assignRole('accountant');

        // 4. ACADEMIC MANAGER
        $academic = User::create([
            'name' => 'Chef UFR Informatique',
            'email' => 'academic@sirh.local',
            'phone' => '+221771234570',
            'password' => Hash::make('Academic@2026'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $academic->assignRole('academic_manager');

        // 5. TEACHERS (5 enseignants)
        $teacherNames = [
            ['name' => 'Dr. Abdoulaye Diallo', 'email' => 'abdoulaye@sirh.local', 'phone' => '+221771234571'],
            ['name' => 'Prof. Mariam Sall', 'email' => 'mariam@sirh.local', 'phone' => '+221771234572'],
            ['name' => 'Dr. Ousmane Ba', 'email' => 'ousmane@sirh.local', 'phone' => '+221771234573'],
            ['name' => 'Prof. Fatou Ndiaye', 'email' => 'fatou@sirh.local', 'phone' => '+221771234574'],
            ['name' => 'Dr. Modou Fall', 'email' => 'modou@sirh.local', 'phone' => '+221771234575'],
        ];

        foreach ($teacherNames as $i => $data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make('Teacher@2026'),
                'status' => 'active',
                'email_verified_at' => now(),
            ]);
            $user->assignRole('teacher');

            // IMPORTANT: Créer le record Teacher associé + PIN code + UFR
            $pinCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            Teacher::create([
                'user_id' => $user->id,
                'employee_id' => 'EMP-' . str_pad($user->id, 4, '0', STR_PAD_LEFT),
                'hourly_rate' => 3500,
                'status' => 'active',
                'pin_code' => Hash::make($pinCode),
                'ufr_id' => $ufrs[$i % $ufrs->count()]->id,
                'hire_date' => now()->subYears(rand(1, 10)),
            ]);
        }
    }

    private function seedAcademicStructure(): void
    {
        // UFRs
        Ufr::create([
            'name' => 'UFR Informatique',
            'code' => 'UFR-IT',
            'description' => 'Unité de Formation et de Recherche en Informatique',
        ]);

        Ufr::create([
            'name' => 'UFR Mathématiques',
            'code' => 'UFR-MATH',
            'description' => 'Unité de Formation et de Recherche en Mathématiques',
        ]);

        // Subjects
        Subject::create(['name' => 'Algorithmique', 'code' => 'ALG-101', 'level' => 'L1']);
        Subject::create(['name' => 'Programmation C', 'code' => 'PROG-101', 'level' => 'L1']);
        Subject::create(['name' => 'Bases de Données', 'code' => 'BD-201', 'level' => 'L2']);
        Subject::create(['name' => 'Web Development', 'code' => 'WEB-301', 'level' => 'L3']);
        Subject::create(['name' => 'Calcul Intégral', 'code' => 'CALC-101', 'level' => 'L1']);

        // Classrooms
        Classroom::create(['name' => 'Amphithéâtre A1', 'code' => 'AMPH-A1', 'capacity' => 150, 'type' => 'amphitheater']);
        Classroom::create(['name' => 'Salle TP-1', 'code' => 'TP-1', 'capacity' => 30, 'type' => 'lab']);
        Classroom::create(['name' => 'Salle TD-2', 'code' => 'TD-2', 'capacity' => 50, 'type' => 'classroom']);
    }

    private function seedSchedules(): void
    {
        $teachers = Teacher::all();
        $subjects = Subject::all();
        $classrooms = Classroom::all();

        $daysOfWeek = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi'];

        foreach ($teachers->slice(0, 3) as $teacher) {
            foreach ($subjects->slice(0, 2) as $subject) {
                Schedule::create([
                    'teacher_id' => $teacher->id,
                    'subject_id' => $subject->id,
                    'classroom_id' => $classrooms->random()->id,
                    'day_of_week' => $daysOfWeek[rand(0, 4)],
                    'start_time' => '09:00:00',
                    'end_time' => '11:00:00',
                    'is_archived' => false,
                ]);
            }
        }
    }

    private function seedAttendance(): void
    {
        $teachers = Teacher::all();
        $schedules = Schedule::all();

        $today = Carbon::now();
        $startOfMonth = $today->copy()->startOfMonth();

        foreach ($teachers as $teacher) {
            $teacherSchedules = $schedules->where('teacher_id', $teacher->id);

            for ($day = 1; $day <= 20; $day++) {
                $date = $startOfMonth->copy()->addDays($day - 1);

                if ($date->isWeekday()) {
                    $schedule = $teacherSchedules->random();
                    if ($schedule) {
                        $status = rand(0, 10) > 1 ? 'present' : (rand(0, 1) ? 'absent' : 'late');

                        Attendance::create([
                            'teacher_id' => $teacher->id,
                            'schedule_id' => $schedule->id,
                            'attendance_date' => $date,
                            'status' => $status,
                            'check_in_time' => $status === 'absent' ? null : $date->setTimeFromTimeString('09:00:00'),
                            'check_out_time' => $status === 'absent' ? null : $date->setTimeFromTimeString('11:00:00'),
                            'hours_worked' => $status === 'absent' ? null : 2,
                        ]);
                    }
                }
            }
        }
    }

    private function seedLeaves(): void
    {
        $leaveTypes = [
            ['name' => 'Congé Annuel', 'code' => 'ANNUAL', 'max_days' => 30],
            ['name' => 'Maladie', 'code' => 'SICK', 'max_days' => 15],
            ['name' => 'Maternité', 'code' => 'MATERNITY', 'max_days' => 90],
        ];

        foreach ($leaveTypes as $type) {
            LeaveType::create($type);
        }

        $teachers = Teacher::all();
        $leaveTypes = LeaveType::all();

        foreach ($teachers as $teacher) {
            foreach ($leaveTypes as $type) {
                LeaveBalance::create([
                    'user_id' => $teacher->user_id,
                    'leave_type_id' => $type->id,
                    'year' => now()->year,
                    'total_days' => $type->max_days,
                    'used_days' => 0,
                    'pending_days' => 0,
                ]);
            }
        }

        foreach ($teachers->slice(0, 2) as $teacher) {
            LeaveRequest::create([
                'user_id' => $teacher->user_id,
                'leave_type_id' => LeaveType::first()->id,
                'start_date' => now()->addDays(5),
                'end_date' => now()->addDays(7),
                'reason' => 'Congé personnel',
                'status' => 'approved',
                'approved_at' => now(),
            ]);
        }
    }

    private function seedPayrolls(): void
    {
        $teachers = Teacher::all();

        foreach ($teachers as $teacher) {
            $teacher->update([
                'hourly_rate' => rand(2500, 5000),
            ]);

            $baseAmount = (rand(30, 50)) * $teacher->hourly_rate;
            $brsAmount = $baseAmount >= 25000 ? round($baseAmount * 0.05, 2) : 0;
            $netSalary = $baseAmount - $brsAmount;

            $payroll = Payroll::create([
                'teacher_id' => $teacher->id,
                'year' => now()->year,
                'month' => now()->month,
                'base_salary' => 0,
                'honorarium_amount' => $baseAmount,
                'brs_amount' => $brsAmount,
                'gross_salary' => $baseAmount,
                'deductions' => $brsAmount,
                'net_salary' => $netSalary,
                'payment_method' => $netSalary >= 100000 ? 'check' : 'cash',
                'status' => ['calculated', 'validated', 'paid'][rand(0, 2)],
            ]);

            PayrollDetail::create([
                'payroll_id' => $payroll->id,
                'description' => 'Honoraire : ' . round($baseAmount / $teacher->hourly_rate, 1) . 'h × ' . number_format($teacher->hourly_rate, 0) . ' F',
                'type' => 'earning',
                'amount' => $baseAmount,
            ]);

            if ($brsAmount > 0) {
                PayrollDetail::create([
                    'payroll_id' => $payroll->id,
                    'description' => 'BRS (5 %)',
                    'type' => 'deduction',
                    'amount' => $brsAmount,
                ]);
            }
        }
    }
}
