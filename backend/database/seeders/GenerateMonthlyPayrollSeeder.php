<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Payroll;
use App\Models\Attendance;
use App\Models\Teacher;
use Carbon\Carbon;

class GenerateMonthlyPayrollSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🔄 Generating payroll for September & October 2026...');

        // Tous les 16 enseignants
        $teachers = Teacher::all();
        $months = [9, 10]; // Septembre et Octobre

        foreach ($months as $month) {
            $this->command->info("\n📅 Processing month: " . $this->getMonthName($month) . " 2026");

            foreach ($teachers as $teacher) {
                // Générer 15 jours de travail par mois
                $attendance_count = 0;
                $total_hours = 0;

                for ($day = 1; $day <= 30; $day++) {
                    // Skip weekends (samedi=6, dimanche=7)
                    $date = Carbon::createFromDate(2026, $month, $day);
                    $dayOfWeek = $date->dayOfWeek; // 0=dimanche, 6=samedi

                    if ($dayOfWeek == 0 || $dayOfWeek == 6) {
                        continue; // Skip weekends
                    }

                    // 65% de chance de travailler
                    if (rand(1, 100) > 65) {
                        continue;
                    }

                    $hours = rand(7, 9) + (rand(0, 1) ? 0.5 : 0); // 7-9.5 heures par jour

                    Attendance::updateOrCreate(
                        [
                            'teacher_id' => $teacher->id,
                            'user_id' => $teacher->user_id,
                            'attendance_date' => $date,
                        ],
                        [
                            'check_in_time' => '08:00:00',
                            'check_out_time' => '16:30:00',
                            'status' => 'present',
                            'hours_worked' => $hours,
                        ]
                    );

                    $total_hours += $hours;
                    $attendance_count++;
                }

                // Générer le bulletin de paie
                if ($attendance_count > 0) {
                    $total_hours = round($total_hours, 1);
                    $gross_salary = $total_hours * (float)($teacher->hourly_rate ?? 5000);
                    $brs_amount = round($gross_salary * 0.05, 0);
                    $net_salary = $gross_salary - $brs_amount;

                    Payroll::updateOrCreate(
                        [
                            'teacher_id' => $teacher->id,
                            'year' => 2026,
                            'month' => $month,
                        ],
                        [
                            'base_salary' => 0,
                            'honorarium_amount' => $gross_salary,
                            'brs_amount' => -$brs_amount,
                            'payment_method' => rand(0, 1) ? 'check' : 'cash',
                            'gross_salary' => $gross_salary,
                            'deductions' => $brs_amount,
                            'net_salary' => $net_salary,
                            'status' => 'validated',
                            'validated_by' => 1,
                            'validated_at' => now(),
                            'calculation_details' => json_encode([
                                'hours' => $total_hours,
                                'hourly_rate' => $teacher->hourly_rate,
                                'deduction_rate' => 0.05,
                                'days_worked' => $attendance_count,
                            ]),
                        ]
                    );

                    $this->command->info("  ✅ {$teacher->user->name}: {$total_hours}h → " . number_format($net_salary, 0) . " XOF");
                }
            }
        }

        $this->command->info("\n🎉 Payroll generation complete!");
    }

    private function getMonthName($month): string
    {
        $months = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
        ];
        return $months[$month] ?? 'Unknown';
    }
}
