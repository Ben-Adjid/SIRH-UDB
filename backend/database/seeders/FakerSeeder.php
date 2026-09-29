<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Teacher;
use App\Models\Ufr;
use App\Models\Subject;
use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\Homework;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Payroll;
use App\Models\Notification;
use App\Models\Document;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class FakerSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('fr_FR');
        $faker->addProvider(new \Faker\Provider\fr_FR\Person($faker));
        $faker->addProvider(new \Faker\Provider\fr_FR\PhoneNumber($faker));
        $faker->addProvider(new \Faker\Provider\fr_FR\Address($faker));

        $levels = ['L1', 'L2', 'M1', 'M2'];
        $days = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
        $statuses = ['active', 'active', 'active', 'active', 'inactive'];
        $attStatuses = ['present', 'present', 'present', 'present', 'late', 'absent'];

        $this->command->info('Génération des UFR...');

        $ufrNames = [
            'Informatique Générale', 'Génie Logiciel', 'Réseaux & Télécoms',
            'Gestion & Finance', 'Marketing Digital', 'Droit des Affaires',
            'Journalisme & Communication', 'Architecture', 'Médecine', 'Sciences de l\'Ingénieur'
        ];
        $ufrIds = [];
        foreach ($ufrNames as $i => $name) {
            $ufr = Ufr::firstOrCreate(
                ['name' => $name],
                [
                    'code' => substr(strtoupper($name), 0, 4) . ($i + 1),
                    'description' => "UFR de $name",
                ]
            );
            $ufrIds[] = $ufr->id;
        }

        $this->command->info('Génération de matières...');

        $subjectData = [
            ['name' => 'Algorithmique Avancée', 'code' => 'ALG-AV', 'level' => 'L2', 'credit_hours' => 4],
            ['name' => 'Base de Données', 'code' => 'BDD', 'level' => 'L2', 'credit_hours' => 3],
            ['name' => 'Réseaux', 'code' => 'RES', 'level' => 'L3', 'credit_hours' => 3],
            ['name' => 'Intelligence Artificielle', 'code' => 'IA', 'level' => 'M1', 'credit_hours' => 5],
            ['name' => 'Développement Web', 'code' => 'WEB', 'level' => 'L2', 'credit_hours' => 4],
            ['name' => 'Cybersécurité', 'code' => 'CYBER', 'level' => 'M2', 'credit_hours' => 5],
            ['name' => 'Marketing', 'code' => 'MKT', 'level' => 'L3', 'credit_hours' => 3],
            ['name' => 'Comptabilité', 'code' => 'COMPTA', 'level' => 'L1', 'credit_hours' => 3],
            ['name' => 'Droit du Travail', 'code' => 'DT', 'level' => 'L3', 'credit_hours' => 2],
            ['name' => 'Anglais des Affaires', 'code' => 'ENG-BUS', 'level' => 'L1', 'credit_hours' => 2],
            ['name' => 'Probabilités & Stats', 'code' => 'PROBA', 'level' => 'L2', 'credit_hours' => 3],
            ['name' => 'Gestion de Projet', 'code' => 'GP', 'level' => 'M1', 'credit_hours' => 4],
            ['name' => 'Machine Learning', 'code' => 'ML', 'level' => 'M2', 'credit_hours' => 5],
            ['name' => 'IoT & Embarqué', 'code' => 'IOT', 'level' => 'M1', 'credit_hours' => 4],
            ['name' => 'Cloud Computing', 'code' => 'CLOUD', 'level' => 'M2', 'credit_hours' => 4],
        ];

        $subjectIds = [];
        foreach ($subjectData as $s) {
            $sub = Subject::create([
                'name' => $s['name'],
                'code' => $s['code'],
                'level' => $s['level'],
                'credit_hours' => $s['credit_hours'],
                'description' => "Cours de {$s['name']} - Niveau {$s['level']}"
            ]);
            $subjectIds[] = $sub->id;
        }

        $this->command->info('Génération de salles de classe...');

        $roomTypes = ['classroom', 'classroom', 'classroom', 'classroom', 'lab', 'lab', 'lab', 'amphitheater', 'amphitheater', 'classroom'];
        $classroomIds = [];
        for ($i = 0; $i < 10; $i++) {
            $type = $roomTypes[$i];
            $prefix = $type === 'amphitheater' ? 'AMPHI' : ($type === 'lab' ? 'LAB' : 'SALLE');
            $room = Classroom::create([
                'name' => "$prefix " . chr(65 + $i),
                'code' => "$prefix-" . ($i + 1),
                'capacity' => $type === 'amphitheater' ? $faker->randomElement([100, 150, 200]) : $faker->randomElement([20, 25, 30, 35, 40]),
                'location' => "Bâtiment " . chr(65 + $faker->numberBetween(0, 4)) . ", Étage " . $faker->numberBetween(0, 3),
                'type' => $type,
            ]);
            $classroomIds[] = $room->id;
        }

        $this->command->info('Génération des utilisateurs et enseignants...');

        $admin = User::where('email', 'admin@sirh.local')->first();
        if (!$admin) {
            $admin = User::create([
                'name' => 'Administrateur Général',
                'email' => 'admin@sirh.local',
                'password' => Hash::make(env('SEED_ADMIN_PASSWORD', 'password')),
                'status' => 'active',
            ]);
            $admin->assignRole('admin');
        }

        $adminId = $admin->id;

        $existingStaff = [
            ['name' => 'Responsable Administratif', 'email' => 'rh@sirh.local', 'role' => 'hr_manager', 'pass' => env('SEED_RH_PASSWORD', 'password')],
            ['name' => 'Secrétaire Administratif', 'email' => 'secretaire@sirh.local', 'role' => 'hr_manager', 'pass' => env('SEED_SECRETARY_PASSWORD', 'password')],
            ['name' => 'Comptable', 'email' => 'compta@sirh.local', 'role' => 'accountant', 'pass' => env('SEED_COMPTA_PASSWORD', 'password')],
            ['name' => 'Chef de UFR', 'email' => 'academic@sirh.local', 'role' => 'academic_manager', 'pass' => env('SEED_ACADEMIC_PASSWORD', 'password')],
        ];

        foreach ($existingStaff as $staff) {
            $u = User::where('email', $staff['email'])->first();
            if (!$u) {
                $u = User::create([
                    'name' => $staff['name'],
                    'email' => $staff['email'],
                    'password' => Hash::make($staff['pass']),
                    'status' => 'active',
                ]);
                $u->assignRole($staff['role']);
            }
        }

        $leaveTypes = LeaveType::all();
        if ($leaveTypes->isEmpty()) {
            $ltData = [
                ['name' => 'Congés Annuels', 'code' => 'AL', 'days_per_year' => 30],
                ['name' => 'Congés Maladie', 'code' => 'SL', 'days_per_year' => 15],
                ['name' => 'Congés Exceptionnels', 'code' => 'EL', 'days_per_year' => 5],
            ];
            foreach ($ltData as $lt) {
                LeaveType::create($lt);
            }
            $leaveTypes = LeaveType::all();
        }

        $teacherIds = [];
        $teacherNames = [];

        $firstNames = ['Amadou', 'Fatou', 'Moussa', 'Aïcha', 'Oumar', 'Khadija', 'Mamadou', 'Rokhaya', 'Ibrahima', 'Ndeye', 'Cheikh', 'Aminata'];
        $lastNames = ['Diallo', 'Sow', 'Ndiaye', 'Ba', 'Faye', 'Diop', 'Fall', 'Gueye', 'Cissé', 'Thiam', 'Mbaye', 'Niang'];

        for ($i = 0; $i < 12; $i++) {
            $firstName = $firstNames[$i % count($firstNames)];
            $lastName = $lastNames[$i % count($lastNames)];
            $fullName = "$firstName $lastName";
            $emailBase = strtolower(\Illuminate\Support\Str::ascii("$firstName.$lastName"));
            $email = "$emailBase@sirh.local";

            if (User::where('email', $email)->exists()) {
                $email = "$emailBase." . ($i + 1) . "@sirh.local";
            }

            $u = User::create([
                'name' => $fullName,
                'email' => $email,
                'password' => Hash::make(env('SEED_DEFAULT_PASSWORD', 'password')),
                'status' => $faker->randomElement($statuses),
            ]);
            $u->assignRole('teacher');

            $teacher = Teacher::create([
                'user_id' => $u->id,
                'ufr_id' => $faker->randomElement($ufrIds),
                'employee_id' => 'FK-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                'pin_code' => str_pad($i + 1000, 4, '0', STR_PAD_LEFT),
                'phone' => '77' . $faker->numberBetween(1000000, 9999999),
                'mobile' => '78' . $faker->numberBetween(1000000, 9999999),
                'address' => $faker->address(),
                'hire_date' => $faker->dateTimeBetween('-5 years', '-6 months')->format('Y-m-d'),
                'volume_hours' => $faker->randomElement([200, 250, 300, 350, 400]),
                'hourly_rate' => $faker->randomElement([5000, 6500, 8000, 10000, 12000]),
                'status' => $faker->randomElement($statuses),
            ]);

            $teacherIds[] = $teacher->id;
            $teacherNames[] = $fullName;

            Document::create([
                'teacher_id' => $teacher->id,
                'name' => 'CV - ' . $fullName,
                'type' => 'cv',
                'file_path' => 'documents/cv_' . $teacher->employee_id . '.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => $faker->numberBetween(100000, 2000000),
                'uploaded_by' => $adminId,
            ]);

            Document::create([
                'teacher_id' => $teacher->id,
                'name' => 'Contrat - ' . $teacher->employee_id,
                'type' => 'contract',
                'file_path' => 'documents/contract_' . $teacher->employee_id . '.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => $faker->numberBetween(500000, 3000000),
                'uploaded_by' => $adminId,
            ]);

            foreach ($leaveTypes as $lt) {
                LeaveBalance::create([
                    'teacher_id' => $teacher->id,
                    'leave_type_id' => $lt->id,
                    'year' => 2026,
                    'total_days' => $lt->days_per_year,
                    'used_days' => $faker->numberBetween(0, 8),
                    'pending_days' => $faker->numberBetween(0, 3),
                ]);
            }

            Notification::create([
                'user_id' => $u->id,
                'title' => 'Bienvenue dans SIRH Dakar',
                'message' => "Cher(e) $fullName, votre compte enseignant a été créé avec succès.",
                'type' => 'success',
                'link' => '/dashboard',
            ]);

            $this->command->info("  -> $fullName ($email) créé(e)");
        }

        $profTest = User::where('email', 'prof@sirh.local')->first();
        if (!$profTest) {
            $u = User::create([
                'name' => 'Enseignant Test',
                'email' => 'prof@sirh.local',
                'password' => Hash::make(env('SEED_PROF_PASSWORD', 'password')),
                'status' => 'active',
            ]);
            $u->assignRole('teacher');
            $t = Teacher::create([
                'user_id' => $u->id,
                'ufr_id' => $ufrIds[0],
                'employee_id' => 'FK-PROF',
                'hire_date' => '2025-09-01',
                'hourly_rate' => 5000,
                'volume_hours' => 300,
                'status' => 'active',
            ]);
            foreach ($leaveTypes as $lt) {
                LeaveBalance::create([
                    'teacher_id' => $t->id, 'leave_type_id' => $lt->id,
                    'year' => 2026, 'total_days' => $lt->days_per_year, 'used_days' => 0,
                ]);
            }
            $teacherIds[] = $t->id;
        }

        $this->command->info('Génération des types d\'enseignement...');

        $this->command->info('Génération des emplois du temps...');

        foreach ($teacherIds as $tid) {
            for ($d = 0; $d < $faker->numberBetween(2, 5); $d++) {
                $day = $faker->randomElement($days);
                $startHour = $faker->randomElement([8, 10, 14, 16]);
                $endHour = $startHour + $faker->randomElement([1, 2, 3]);

                try {
                    Schedule::create([
                        'teacher_id' => $tid,
                        'subject_id' => $faker->randomElement($subjectIds),
                        'classroom_id' => $faker->randomElement($classroomIds),
                        'start_date' => '2026-01-05',
                        'end_date' => '2026-07-31',
                        'day_of_week' => $day,
                        'start_time' => sprintf('%02d:00', $startHour),
                        'end_time' => sprintf('%02d:00', $endHour),
                        'status' => 'scheduled',
                        'level' => $faker->randomElement($levels),
                        'semester' => $faker->randomElement(['S1', 'S2']),
                        'academic_year_id' => \App\Models\AcademicYear::where('is_active', true)->first()?->id,
                    ]);
                } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                } catch (\Illuminate\Database\QueryException $e) {
                }
            }
        }

        $this->command->info('Génération des devoirs...');

        for ($i = 0; $i < 20; $i++) {
            $tid = $faker->randomElement($teacherIds);
            Homework::create([
                'teacher_id' => $tid,
                'subject_id' => $faker->randomElement($subjectIds),
                'classroom_id' => $faker->randomElement($classroomIds),
                'title' => $faker->randomElement([
                    'Devoir sur table', 'Examen Partiel', 'Projet', 'TD Noté',
                    'QCM', 'Étude de cas', 'Présentation', 'Rapport'
                ]) . ' - ' . $faker->word(),
                'description' => $faker->sentence(10),
                'scheduled_date' => $faker->dateTimeBetween('-1 month', '+2 months')->format('Y-m-d'),
                'start_time' => '08:00',
                'end_time' => '10:00',
                'duration_minutes' => 120,
                'status' => $faker->randomElement(['planned', 'published', 'completed', 'approved']),
                'max_score' => $faker->randomElement([20, 20, 20, 20, 20, 100]),
            ]);
        }

        $this->command->info('Génération des présences...');

        foreach ($teacherIds as $tid) {
            $teacherUserId = Teacher::find($tid)?->user_id;
            $count = 0;
            $day = 0;
            while ($count < 15) {
                $date = now()->subDays($day);
                if (!$date->isWeekend()) {
                    $status = $faker->randomElement($attStatuses);
                    $checkIn = match ($status) {
                        'late' => '08:' . $faker->numberBetween(15, 45) . ':00',
                        default => '07:' . $faker->numberBetween(30, 55) . ':00',
                    };
                    $checkOut = '17:' . $faker->numberBetween(0, 30) . ':00';

                    Attendance::create([
                        'user_id' => $teacherUserId,
                        'teacher_id' => $tid,
                        'attendance_date' => $date->format('Y-m-d'),
                        'check_in_time' => $checkIn,
                        'check_out_time' => $status !== 'absent' ? $checkOut : null,
                        'status' => $status,
                        'hours_worked' => $status !== 'absent' ? $faker->randomFloat(1, 7, 9.5) : 0,
                    ]);
                    $count++;
                }
                $day++;
            }
        }

        $this->command->info('Génération des congés...');

        foreach ($teacherIds as $tid) {
            if ($faker->boolean(40)) {
                $lt = $faker->randomElement($leaveTypes);
                $start = $faker->dateTimeBetween('-2 months', '+3 months');
                $duration = $faker->numberBetween(1, 5);
                $end = (clone $start)->modify("+$duration days");

                LeaveRequest::create([
                    'teacher_id' => $tid,
                    'leave_type_id' => $lt->id,
                    'start_date' => $start->format('Y-m-d'),
                    'end_date' => $end->format('Y-m-d'),
                    'duration_days' => $duration,
                    'status' => $faker->randomElement(['pending', 'approved', 'approved', 'rejected']),
                    'reason' => $faker->sentence(6),
                    'approved_by' => $start < now() ? $adminId : null,
                    'approved_at' => $start < now() ? $faker->dateTimeBetween('-3 months', '-1 day') : null,
                ]);
            }
        }

        $this->command->info('Génération des fiches de paie...');

        $teachers = Teacher::all();
        foreach ($teachers as $teacher) {
            for ($m = 1; $m <= 3; $m++) {
                if (Payroll::where('teacher_id', $teacher->id)->where('month', $m)->where('year', 2026)->exists()) {
                    continue;
                }
                $base = ($teacher->hourly_rate ?? 5000) * ($teacher->volume_hours ?? 300);
                $bonus = $faker->randomElement([0, 25000, 50000, 75000, 100000]);
                $deductions = $base * $faker->randomFloat(2, 0.05, 0.15);
                $gross = $base + $bonus;
                $net = $gross - $deductions;

                Payroll::create([
                    'teacher_id' => $teacher->id,
                    'month' => $m,
                    'year' => 2026,
                    'honorarium_amount' => $base,
                    'brs_amount' => 0,
                    'gross_salary' => $gross,
                    'deductions' => round($deductions, 2),
                    'net_salary' => round($net, 2),
                    'status' => $m < now()->month ? 'paid' : 'draft',
                    'validated_at' => $m < now()->month ? now()->subMonths(now()->month - $m) : null,
                ]);
            }
        }

        $this->command->info('Notifications globales...');

        Notification::create([
            'user_id' => $adminId,
            'title' => 'Base de données initialisée',
            'message' => 'Les données de test ont été générées avec Faker (' . Teacher::count() . ' enseignants).',
            'type' => 'success',
            'link' => '/dashboard',
        ]);

        $this->command->info('');
        $this->command->info('✅ Génération Faker terminée !');
        $this->command->info("   - " . Ufr::count() . " départements");
        $this->command->info("   - " . Subject::count() . " matières");
        $this->command->info("   - " . Classroom::count() . " salles");
        $this->command->info("   - " . User::count() . " utilisateurs");
        $this->command->info("   - " . Teacher::count() . " enseignants");
        $this->command->info("   - " . Schedule::count() . " créneaux");
        $this->command->info("   - " . Homework::count() . " devoirs");
        $this->command->info("   - " . Attendance::count() . " présences");
        $this->command->info("   - " . LeaveRequest::count() . " demandes de congé");
        $this->command->info("   - " . Payroll::count() . " fiches de paie");
    }
}
