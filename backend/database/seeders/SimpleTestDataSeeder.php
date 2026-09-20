<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Teacher;
use App\Models\Attendance;
use App\Models\Payroll;
use Carbon\Carbon;

class SimpleTestDataSeeder extends Seeder
{
    public function run(): void
    {
        echo "\n📚 Création de données de test simples...\n";

        $teachers = Teacher::with('user')->limit(3)->get();
        if ($teachers->count() < 1) {
            echo "❌ Aucun enseignant trouvé\n";
            return;
        }

        echo "✅ Création des pointages pour " . $teachers->count() . " enseignants...\n";

        foreach ($teachers as $teacher) {
            for ($i = 0; $i < 5; $i++) {
                $date = Carbon::now()->subDays($i);

                Attendance::firstOrCreate([
                    'teacher_id' => $teacher->id,
                    'user_id' => $teacher->user_id,
                    'attendance_date' => $date,
                ], [
                    'check_in_time' => $date->copy()->setHour(8)->setMinute(0),
                    'check_out_time' => $date->copy()->setHour(10)->setMinute(0),
                    'status' => 'present',
                ]);
            }
            echo "  ✓ 5 pointages créés pour: " . $teacher->user->name . "\n";
        }

        echo "\n✅ Création des paies...\n";

        foreach ($teachers as $teacher) {
            $month = Carbon::now()->month;
            $year = Carbon::now()->year;

            $hoursWorked = Attendance::where('teacher_id', $teacher->id)
                ->whereYear('attendance_date', $year)
                ->whereMonth('attendance_date', $month)
                ->sum('hours_worked') ?: 20;

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
            ]);

            echo "  ✓ " . $teacher->user->name . " - " . number_format($netSalary, 0, ',', ' ') . " F\n";
        }

        echo "\n✅ Données de test créées!\n";
    }
}
