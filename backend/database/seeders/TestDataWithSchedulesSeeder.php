<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Teacher;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\Classroom;
use App\Models\Ufr;
use App\Models\Attendance;
use App\Models\Payroll;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class TestDataWithSchedulesSeeder extends Seeder
{
    public function run(): void
    {
        echo "\n📚 Création des données de test avec horaires...\n";

        // Récupérer les UFRs
        $ufrs = Ufr::all();
        if ($ufrs->isEmpty()) {
            echo "❌ Erreur: Aucun UFR trouvé. Exécutez d'abord CompleteSeeder\n";
            return;
        }

        // Récupérer les sujets
        $subjects = Subject::all();
        $classrooms = Classroom::all();

        // Récupérer les enseignants
        $teachers = Teacher::with('user')->limit(3)->get();

        if ($teachers->count() < 1) {
            echo "❌ Erreur: Aucun enseignant trouvé\n";
            return;
        }

        echo "✅ Création des horaires pour " . $teachers->count() . " enseignants...\n";

        // Créer des horaires pour les enseignants
        foreach ($teachers as $teacher) {
            if ($subjects->isEmpty() || $classrooms->isEmpty()) {
                continue;
            }

            // 2 cours par semaine pendant 2 semaines
            for ($week = 0; $week < 2; $week++) {
                for ($day = 1; $day <= 2; $day++) {
                    $startDate = Carbon::now()->addWeeks($week)->startOfWeek()->addDays($day - 1);

                    Schedule::firstOrCreate([
                        'teacher_id' => $teacher->id,
                        'subject_id' => $subjects->random()->id,
                        'classroom_id' => $classrooms->random()->id,
                        'start_date' => $startDate,
                        'end_date' => $startDate->copy()->addMonths(1),
                    ], [
                        'start_time' => '08:00',
                        'end_time' => '10:00',
                        'day_of_week' => $startDate->dayOfWeek,
                        'level' => null,
                        'status' => 'scheduled',
                    ]);

                    echo "  ✓ Horaire créé: " . $teacher->user->name . " - " . $startDate->format('Y-m-d') . "\n";
                }
            }
        }

        echo "\n✅ Création des pointages...\n";

        // Créer des pointages pour les derniers 5 jours
        foreach ($teachers as $teacher) {
            for ($i = 0; $i < 5; $i++) {
                $date = Carbon::now()->subDays($i);

                // Vérifier s'il y a un horaire ce jour
                $schedule = Schedule::where('teacher_id', $teacher->id)
                    ->where('day_of_week', $date->dayOfWeek)
                    ->first();

                if (!$schedule) {
                    continue;
                }

                Attendance::firstOrCreate([
                    'teacher_id' => $teacher->id,
                    'user_id' => $teacher->user_id,
                    'attendance_date' => $date,
                    'schedule_id' => $schedule->id,
                ], [
                    'check_in_time' => $date->copy()->setHour(8)->setMinute(5),
                    'check_out_time' => $date->copy()->setHour(10)->setMinute(0),
                    'status' => 'present',
                    'hours_worked' => 2.0,
                    'justification_notes' => null,
                ]);

                echo "  ✓ Pointage: " . $teacher->user->name . " - " . $date->format('Y-m-d') . " (Présent)\n";
            }
        }

        echo "\n✅ Création des données de paie...\n";

        // Créer des paies pour les enseignants
        foreach ($teachers as $teacher) {
            $month = Carbon::now()->month;
            $year = Carbon::now()->year;

            // Vérifier les heures travaillées
            $hoursWorked = Attendance::where('teacher_id', $teacher->id)
                ->whereYear('attendance_date', $year)
                ->whereMonth('attendance_date', $month)
                ->sum('hours_worked');

            $hoursWorked = max($hoursWorked, 20); // Minimum 20 heures

            $baseAmount = $hoursWorked * ($teacher->hourly_rate ?? 5000);
            $brsAmount = $baseAmount >= 25000 ? round($baseAmount * 0.05, 2) : 0;
            $netSalary = $baseAmount - $brsAmount;
            $paymentMethod = $netSalary >= 100000 ? 'check' : 'cash';

            Payroll::firstOrCreate([
                'teacher_id' => $teacher->id,
                'year' => $year,
                'month' => $month,
            ], [
                'honorarium_amount' => $baseAmount,
                'brs_amount' => $brsAmount,
                'gross_salary' => $baseAmount,
                'deductions' => $brsAmount,
                'net_salary' => $netSalary,
                'payment_method' => $paymentMethod,
                'status' => 'calculated',
                'hours_worked' => $hoursWorked,
            ]);

            echo "  ✓ Paie: " . $teacher->user->name . " - " . number_format($netSalary, 0, ',', ' ') . " F\n";
        }

        echo "\n✅ Données de test créées avec succès!\n";
        echo "   - " . Schedule::count() . " horaires\n";
        echo "   - " . Attendance::count() . " pointages\n";
        echo "   - " . Payroll::count() . " paies\n";
    }
}
