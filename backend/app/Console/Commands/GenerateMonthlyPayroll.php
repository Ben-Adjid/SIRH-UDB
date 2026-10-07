<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Payroll;
use App\Models\Attendance;
use App\Models\Teacher;
use Carbon\Carbon;

class GenerateMonthlyPayroll extends Command
{
    protected $signature = 'payroll:generate {month? : Month (1-12)} {year? : Year (default: current)}';
    protected $description = 'Generate monthly payroll based on attendance records';

    public function handle()
    {
        $month = $this->argument('month') ?? now()->month;
        $year = $this->argument('year') ?? now()->year;

        $this->info("📅 Generating payroll for " . Carbon::createFromDate($year, $month, 1)->format('F Y'));

        $teachers = Teacher::all();
        $generated = 0;

        foreach ($teachers as $teacher) {
            $total_hours = Attendance::where('teacher_id', $teacher->id)
                ->whereYear('attendance_date', $year)
                ->whereMonth('attendance_date', $month)
                ->sum('hours_worked');

            if ($total_hours == 0) {
                $this->warn("  ⚠️ {$teacher->user->name}: No attendance records");
                continue;
            }

            $gross_salary = $total_hours * (float)($teacher->hourly_rate ?? 5000);
            $brs_amount = round($gross_salary * 0.05, 0);
            $net_salary = $gross_salary - $brs_amount;

            Payroll::updateOrCreate(
                [
                    'teacher_id' => $teacher->id,
                    'year' => $year,
                    'month' => $month,
                ],
                [
                    'base_salary' => 0,
                    'honorarium_amount' => $gross_salary,
                    'brs_amount' => -$brs_amount,
                    'payment_method' => 'check',
                    'gross_salary' => $gross_salary,
                    'deductions' => $brs_amount,
                    'net_salary' => $net_salary,
                    'status' => 'draft',
                    'calculation_details' => json_encode(['hours' => round($total_hours, 1)]),
                ]
            );

            $this->info("  ✅ {$teacher->user->name}: " . round($total_hours, 1) . "h → " . number_format($net_salary, 0) . " XOF");
            $generated++;
        }

        $this->info("\n🎉 Generated $generated payroll records");
    }
}
