<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Teacher;
use App\Models\Ufr;
use App\Models\Subject;
use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\AcademicYear;
use App\Models\TeachingType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🎓 Création des UFR...');

        $ufrData = [
            ['name' => 'Génie Juridique', 'code' => 'UFR-GJ'],
            ['name' => 'Génie Gestion Economique', 'code' => 'UFR-GGE'],
            ['name' => 'Sciences et Technologies', 'code' => 'UFR-ST'],
            ['name' => 'Langues et Affaires', 'code' => 'UFR-LA'],
        ];

        $ufrIds = [];
        foreach ($ufrData as $ufr) {
            $model = Ufr::firstOrCreate(['code' => $ufr['code']], $ufr);
            $ufrIds[] = $model->id;
        }

        $this->command->info('👨‍🏫 Création des enseignants...');

        $teacherByUfr = [];
        foreach ($ufrIds as $ufrIdx => $ufrId) {
            $teacherByUfr[$ufrId] = [];

            // Réutilise les enseignants existants (prof1, prof2, prof3...) s'il y en a assez
            $existingInUfr = Teacher::where('ufr_id', $ufrId)->get();
            if ($existingInUfr->count() >= 3) {
                foreach ($existingInUfr->take(3) as $et) {
                    $teacherByUfr[$ufrId][] = $et->id;
                }
                continue;
            }
            foreach ($existingInUfr as $et) {
                $teacherByUfr[$ufrId][] = $et->id;
            }

            for ($t = 0; count($teacherByUfr[$ufrId]) < 3; $t++) {
                $names = [
                    ['Amadou', 'Diallo'], ['Fatou', 'Sow'], ['Moussa', 'Ndiaye'],
                    ['Aïcha', 'Ba'], ['Oumar', 'Faye'], ['Khadija', 'Diop'],
                    ['Mamadou', 'Fall'], ['Rokhaya', 'Gueye'], ['Ibrahima', 'Cissé'],
                    ['Ndeye', 'Thiam'], ['Cheikh', 'Mbaye'], ['Aminata', 'Niang'],
                ];
                $nameIdx = ($ufrIdx * 3 + $t) % count($names);
                $fn = $names[$nameIdx][0];
                $ln = $names[$nameIdx][1];
                $email = strtolower("$fn.$ln" . ($ufrIdx + 1) . $t . "@sirh.local");

                if (User::where('email', $email)->exists()) {
                    $existingTeacher = User::where('email', $email)->first()->teacher;
                    if ($existingTeacher && $existingTeacher->ufr_id == $ufrId) {
                        $teacherByUfr[$ufrId][] = $existingTeacher->id;
                    }
                    continue;
                }

                $u = User::create([
                    'name' => "$fn $ln",
                    'email' => $email,
                    'password' => Hash::make(env('SEED_TEACHER_PASSWORD', 'password')),
                    'status' => 'active',
                ]);
                $u->assignRole('teacher');

                try {
                    $teacher = Teacher::create([
                        'user_id' => $u->id,
                        'ufr_id' => $ufrId,
                        'employee_id' => 'SD-' . str_pad(Teacher::max('id') + 1, 4, '0', STR_PAD_LEFT),
                        'pin_code' => $this->generatePin(),
                        'phone' => '77' . rand(1000000, 9999999),
                        'hire_date' => now()->subYears(rand(1, 5))->format('Y-m-d'),
                        'volume_hours' => 200,
                        'hourly_rate' => 5000,
                        'status' => 'active',
                    ]);
                    $teacherByUfr[$ufrId][] = $teacher->id;
                } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                    $existingTeacher = Teacher::where('user_id', $u->id)->first();
                    if ($existingTeacher) {
                        $teacherByUfr[$ufrId][] = $existingTeacher->id;
                    }
                }
            }
        }

        $this->command->info('📖 Création des matières par niveau...');

        $subjectsByUfr = [
            0 => [
                'L1' => [['Introduction au Droit', 'INT-D'], ['Droit Constitutionnel', 'DCON'], ['Organisation Judiciaire', 'ORG-J']],
                'L2' => [['Droit Civil', 'D-CIVIL'], ['Droit Pénal', 'D-PENAL'], ['Droit Administratif', 'D-ADM']],
                'L3' => [['Droit des Affaires', 'D-AFF'], ['Droit International', 'D-INT'], ['Contentieux', 'CONT']],
                'M1' => [['Droit Fiscal', 'D-FISC'], ['Droit du Travail', 'D-TRAV'], ['Droit Public Approfondi', 'DPA']],
                'M2' => [['Droit Bancaire', 'D-BANC'], ['Arbitrage et Médiation', 'ARB-MED'], ['Déontologie', 'DEONT']],
            ],
            1 => [
                'L1' => [['Économie Générale', 'ECO-G'], ['Comptabilité Générale', 'COMPTA'], ['Mathématiques Financières', 'MATH-F']],
                'L2' => [['Macroéconomie', 'MACRO'], ['Gestion des Entreprises', 'GEST-E'], ['Statistiques Appliquées', 'STAT']],
                'L3' => [['Finance d\'Entreprise', 'FIN-E'], ['Marketing', 'MKT'], ['Comptabilité Analytique', 'COMP-A']],
                'M1' => [['Audit et Contrôle', 'AUDIT'], ['Management Stratégique', 'MS'], ['Gestion des Ressources Humaines', 'GRH']],
                'M2' => [['Entrepreneuriat', 'ENTREP'], ['Économétrie', 'ECONO'], ['Finance Internationale', 'FIN-INT']],
            ],
            2 => [
                'L1' => [['Mathématiques Générales', 'MATH-G'], ['Physique Générale', 'PHYS-G'], ['Introduction à l\'Informatique', 'INFO-I']],
                'L2' => [['Algèbre Linéaire', 'ALG-L'], ['Électricité', 'ELEC'], ['Programmation', 'PROG']],
                'L3' => [['Analyse Numérique', 'ANA-N'], ['Électronique', 'ELECTRO'], ['Base de Données', 'BDD']],
                'M1' => [['Réseaux et Télécoms', 'RESEAUX'], ['Intelligence Artificielle', 'IA'], ['Systèmes Embarqués', 'SE']],
                'M2' => [['Cybersécurité', 'CYBER'], ['Big Data', 'BIGDATA'], ['Génie Logiciel', 'GEN-LOG']],
            ],
            3 => [
                'L1' => [['Anglais Général', 'ENG-G'], ['Français Appliqué', 'FR-APP'], ['Techniques de Communication', 'TECH-COM']],
                'L2' => [['Anglais des Affaires', 'ENG-AFF'], ['Expression Écrite', 'EXP-ECR'], ['Commerce International', 'COM-INT']],
                'L3' => [['Traduction Anglais', 'TRAD-ANG'], ['Négociation', 'NEGO'], ['Marketing Digital', 'MKT-DIG']],
                'M1' => [['Traduction Juridique', 'TRAD-JUR'], ['Communication d\'Entreprise', 'COM-ENT'], ['Langues et Cultures', 'LANG-CULT']],
                'M2' => [['Interprétariat', 'INTERP'], ['Stratégie de Communication', 'STRAT-COM'], ['Management Interculturel', 'MAN-INTER']],
            ],
        ];

        $subjectIds = [];
        foreach ($subjectsByUfr as $ufrIdx => $levels) {
            $subjectIds[$ufrIdx] = [];
            foreach ($levels as $level => $subjects) {
                foreach ($subjects as [$name, $code]) {
                    $existing = Subject::where('name', $name)->first();
                    if ($existing) {
                        $sub = $existing;
                    } else {
                        $sub = Subject::firstOrCreate(
                            ['code' => $code],
                            ['name' => $name, 'level' => $level, 'credit_hours' => rand(2, 5)]
                        );
                    }
                    $subjectIds[$ufrIdx][$level][] = $sub->id;
                }
            }
        }

        $this->command->info('🏫 Création des salles...');

        $classroomsByType = ['amphitheater' => [], 'classroom' => [], 'lab' => []];
        $classroomAllIds = [];

        $amphitheaterCount = 0;
        $classroomCount = 0;
        $labCount = 0;
        for ($i = 0; $i < 60; $i++) {
            if ($classroomCount < 24) {
                $type = 'classroom';
                $prefix = 'SALLE';
                $classroomCount++;
            } elseif ($labCount < 20) {
                $type = 'lab';
                $prefix = 'LAB';
                $labCount++;
            } else {
                $type = 'amphitheater';
                $prefix = 'AMPHI';
                $amphitheaterCount++;
            }

            $num = $i + 1;
            $room = Classroom::firstOrCreate(
                ['code' => "$prefix-$num"],
                [
                    'name' => "$prefix $num",
                    'capacity' => $type === 'amphitheater' ? rand(100, 250) : ($type === 'lab' ? rand(20, 40) : rand(25, 50)),
                    'location' => "Bâtiment " . chr(65 + ($i % 5)) . ", Étage " . ($i % 4),
                    'type' => $type,
                ]
            );
            $classroomsByType[$type][] = $room->id;
            $classroomAllIds[] = $room->id;
        }

        $amphiIds = $classroomsByType['amphitheater'];
        $salleIds = $classroomsByType['classroom'];
        $labIds = $classroomsByType['lab'];

        $activeYear = AcademicYear::where('is_active', true)->first();
        if (!$activeYear) {
            $activeYear = AcademicYear::firstOrCreate(
                ['name' => '2025-2026'],
                ['start_year' => 2025, 'end_year' => 2026, 'is_active' => true]
            );
        }

        $this->command->info('🕐 Création des emplois du temps complets...');

        $teachingTypeMap = [];
        foreach (TeachingType::all() as $tt) {
            $teachingTypeMap[$tt->code] = $tt->id;
        }

        $days = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
        $levels = ['L1', 'L2', 'L3', 'M1', 'M2'];
        $semesters = ['S1', 'S2'];

        $allSlots = [
            ['08:00', '10:00'],
            ['10:00', '12:00'],
            ['14:00', '16:00'],
            ['16:00', '18:00'],
        ];

        $s2StartOffset = ['08:00' => '09:00', '10:00' => '11:00', '14:00' => '15:00', '16:00' => '17:00'];
        $s2EndOffset = ['10:00' => '11:00', '12:00' => '13:00', '16:00' => '17:00', '18:00' => '19:00'];

        $count = 0;
        $skipped = 0;
        $amphiPool = $amphiIds;
        $sallePool = $salleIds;
        $labPool = $labIds;

        $roomCounters = ['amphi' => 0, 'salle' => 0, 'lab' => 0];
        $booked = [];

        $plan = [
            ['CM', 0, 0], ['TD', 0, 1],
            ['CM', 1, 0], ['TP', 1, 2],
            ['CM', 2, 2], ['TD', 2, 3],
            ['TD', 3, 0], ['TP', 3, 3],
            ['TP', 4, 0], ['CM', 4, 2],
            ['TD', 5, 0], ['TP', 5, 2],
        ];

        foreach ($semesters as $sem) {
            $sDate = $sem === 'S1' ? '2025-10-01' : '2026-01-15';
            $eDate = $sem === 'S1' ? '2026-01-31' : '2026-06-30';
            $booked[$sem] = [];

            $roomCounters = ['amphi' => 0, 'salle' => 0, 'lab' => 0];

            foreach ($ufrIds as $ufrIdx => $ufrId) {
                $teachersForUfr = [];
                foreach (($teacherByUfr[$ufrId] ?? []) as $tId) {
                    $teachersForUfr[] = $tId;
                }
                if (empty($teachersForUfr)) continue;

                foreach ($levels as $levelIdx => $level) {
                    $subsForLevel = $subjectIds[$ufrIdx][$level] ?? [];
                    if (count($subsForLevel) < 2) continue;

                    $subA = $subsForLevel[0];
                    $subB = $subsForLevel[1];
                    $subC = $subsForLevel[2] ?? $subsForLevel[0];

                    $teacherA = $teachersForUfr[$levelIdx % count($teachersForUfr)];
                    $teacherB = $teachersForUfr[($levelIdx + 1) % count($teachersForUfr)];
                    $teacherC = $teachersForUfr[($levelIdx + 2) % count($teachersForUfr)];

                    $teacherPlan = [
                        [$subA, $teacherA], [$subB, $teacherB],
                        [$subC, $teacherC], [$subA, $teacherA],
                        [$subB, $teacherB], [$subC, $teacherC],
                        [$subA, $teacherA], [$subB, $teacherB],
                        [$subC, $teacherC], [$subA, $teacherA],
                        [$subB, $teacherB], [$subC, $teacherC],
                    ];

                    foreach ($plan as $pIdx => [$sessType, $dayIdx, $slotIdx]) {
                        $day = $days[($dayIdx + $levelIdx) % count($days)];
                        $slot = $allSlots[$slotIdx % count($allSlots)];
                        [$start, $end] = $slot;
                        if ($sem === 'S2') {
                            $start = $s2StartOffset[$start];
                            $end = $s2EndOffset[$end];
                        }
                        [$subId, $teachId] = $teacherPlan[$pIdx];

                        $pool = $sessType === 'CM' ? $amphiPool : ($sessType === 'TP' ? $labPool : $sallePool);
                        $poolKey = $sessType === 'CM' ? 'amphi' : ($sessType === 'TP' ? 'lab' : 'salle');
                        $poolSize = count($pool);

                        $classroomId = null;
                        for ($try = 0; $try < $poolSize; $try++) {
                            $idx = $roomCounters[$poolKey] % $poolSize;
                            $roomCounters[$poolKey]++;
                            $key = "$sem-$pool[$idx]-$day-$start";
                            if (!isset($booked[$sem][$key])) {
                                $classroomId = $pool[$idx];
                                $booked[$sem][$key] = true;
                                break;
                            }
                        }

                        if ($classroomId === null) {
                            $skipped++;
                            continue;
                        }

                        try {
                            Schedule::create([
                                'teacher_id' => $teachId,
                                'subject_id' => $subId,
                                'teaching_type_id' => $teachingTypeMap[$sessType] ?? null,
                                'classroom_id' => $classroomId,
                                'academic_year_id' => $activeYear->id,
                                'level' => $level,
                                'semester' => $sem,
                                'start_date' => $sDate,
                                'end_date' => $eDate,
                                'day_of_week' => $day,
                                'start_time' => $start,
                                'end_time' => $end,
                                'status' => 'scheduled',
                                'type' => 'course',
                            ]);
                            $count++;
                        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                            $skipped++;
                        } catch (\Illuminate\Database\QueryException $e) {
                            $skipped++;
                        }
                    }
                }
            }
        }

        $this->command->info('');
        $this->command->info("✅ Emplois du temps créés avec succès !");
        $this->command->info("   - " . Ufr::count() . " UFR");
        $this->command->info("   - " . Subject::count() . " matières");
        $this->command->info("   - " . Teacher::count() . " enseignants");
        $this->command->info("   - " . Classroom::count() . " salles");
        $this->command->info("   - $count créneaux d'emploi du temps (CM + TD + TP)");
        if ($skipped > 0) {
            $this->command->info("   - $skipped conflits de salles ignorés");
        }
    }

    private function generatePin(): string
    {
        do {
            $pin = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Teacher::where('pin_code', $pin)->exists());

        return $pin;
    }
}
