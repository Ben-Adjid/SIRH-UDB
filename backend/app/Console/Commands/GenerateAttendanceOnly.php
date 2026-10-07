<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Attendance;
use App\Models\Teacher;
use Carbon\Carbon;

class GenerateAttendanceOnly extends Command
{
    protected $signature = 'attendance:generate {month? : Month (1-12)} {year? : Year}';
    protected $description = 'Generate attendance records ONLY (no payroll)';

    public function handle()
    {
        $month = $this->argument('month') ?? now()->month;
        $year = $this->argument('year') ?? now()->year;
        $monthName = Carbon::createFromDate($year, $month, 1)->format('F Y');

        $this->info("═════════════════════════════════════════");
        $this->info("📅 Generating ATTENDANCE for $monthName");
        $this->info("   (No payroll - you'll calculate it manually)");
        $this->info("═════════════════════════════════════════\n");

        $teachers = Teacher::all();
        $daysInMonth = Carbon::createFromDate($year, $month, 1)->daysInMonth;
        $attendance_count = 0;

        foreach ($teachers as $teacher) {
            $working_days = 0;
            $month_total_hours = 0;

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

        $total_attendance = Attendance::whereMonth('attendance_date', $month)
            ->whereYear('attendance_date', $year)
            ->count();

        $this->info("\n═════════════════════════════════════════");
        $this->info("📊 ATTENDANCE FOR $monthName:");
        $this->info("   • Total Records: $total_attendance");
        $this->info("═════════════════════════════════════════\n");
        $this->info("📝 Now YOU can manually calculate payroll for each professor!");
    }
}
