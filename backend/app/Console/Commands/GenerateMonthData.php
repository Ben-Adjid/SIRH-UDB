<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Payroll;
use App\Models\Attendance;
use App\Models\Teacher;
use Carbon\Carbon;

class GenerateMonthData extends Command
{
    protected $signature = 'month:generate {month? : Month (1-12)} {year? : Year}';
    protected $description = 'Generate both attendance and payroll for a month';

    public function handle()
    {
        $month = $this->argument('month') ?? now()->month;
        $year = $this->argument('year') ?? now()->year;
        $monthName = Carbon::createFromDate($year, $month, 1)->format('F Y');

        $this->info("═════════════════════════════════════════");
        $this->info("📅 Generating data for $monthName");
        $this->info("═════════════════════════════════════════\n");

        $teachers = Teacher::all();
        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;

        // Step 1: Generate Attendance
        $this->info("Step 1: Generating attendance records...");
        $attendance_count = 0;

        foreach ($teachers as $teacher) {
            $month_total_hours = 0;
            $working_days = 0;

            // Generate 12-18 working days
            $target_days = rand(12, 18);

            for ($i = 0; $i < $target_days; $i++) {
                $day = rand(1, $daysInMonth);
                $date = Carbon::createFromDate($year, $month, $day);

                // Skip weekends
                if ($date->dayOfWeek == 0 || $date->dayOfWeek == 6) {
                    continue;
                }

                // Check if already exists
                $existing = Attendance::where('teacher_id', $teacher->id)
                    ->where('attendance_date', $date->toDateString())
                    ->exists();

                if ($existing) continue;

                $hours = rand(7, 9) + (rand(0, 1) ? 0.5 : 0);

                Attendance::create([
                    'teacher_id' => $teacher->id,
                    'user_id' => $teacher->user_id,
                    'attendance_date' => $date,
                    'check_in_time' => '08:00:00',
                    'check_out_time' => '16:30:00',
                    'status' => 'present',
                    'hours_worked' => $hours,
                ]);

                $month_total_hours += $hours;
                $working_days++;
                $attendance_count++;
            }

            if ($working_days > 0) {
                $this->line("  ✅ {$teacher->user->name}: {$working_days} days, " . round($month_total_hours, 1) . "h");
            }
        }

        $this->info("\n✅ Generated $attendance_count attendance records\n");

        // Step 2: Generate Payroll
        $this->info("Step 2: Generating payroll records...");
        $payroll_count = 0;

        foreach ($teachers as $teacher) {
            $total_hours = Attendance::where('teacher_id', $teacher->id)
                ->whereYear('attendance_date', $year)
                ->whereMonth('attendance_date', $month)
                ->sum('hours_worked');

            if ($total_hours == 0) continue;

            $gross = $total_hours * (float)($teacher->hourly_rate ?? 5000);
            $brs = round($gross * 0.05, 0);
            $net = $gross - $brs;

            Payroll::updateOrCreate(
                [
                    'teacher_id' => $teacher->id,
                    'year' => $year,
                    'month' => $month,
                ],
                [
                    'base_salary' => 0,
                    'honorarium_amount' => $gross,
                    'brs_amount' => -$brs,
                    'payment_method' => rand(0, 1) ? 'check' : 'cash',
                    'gross_salary' => $gross,
                    'deductions' => $brs,
                    'net_salary' => $net,
                    'status' => 'draft',
                    'calculation_details' => json_encode(['hours' => round($total_hours, 1)]),
                ]
            );

            $this->line("  ✅ {$teacher->user->name}: " . round($total_hours, 1) . "h → " . number_format($net, 0) . " XOF");
            $payroll_count++;
        }

        $this->info("\n✅ Generated $payroll_count payroll records\n");

        // Summary
        $total_attendance = Attendance::whereMonth('attendance_date', $month)
            ->whereYear('attendance_date', $year)
            ->count();
        $total_payroll = Payroll::where('month', $month)->where('year', $year)->sum('net_salary');

        $this->info("═════════════════════════════════════════");
        $this->info("📊 SUMMARY FOR $monthName:");
        $this->info("   • Attendance: $total_attendance records");
        $this->info("   • Payroll: $payroll_count professors");
        $this->info("   • Total Salary: " . number_format($total_payroll, 0) . " XOF");
        $this->info("═════════════════════════════════════════\n");
        $this->info("🎉 Month data generation complete!");
    }
}
