<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\Schedule;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo data for the honoraria screens (safe to re-run):
 * - August 2026: teachers 1-3 clocked in on their courses and were paid.
 * - September 2026: teachers 1-3 and 5-7 clocked in; no payroll yet, to be calculated from the UI.
 *
 * php artisan db:seed --class=PayrollDemoSeeder
 */
class PayrollDemoSeeder extends Seeder
{
    private const MARKER = '[DEMO] Pointage de démonstration';
    private const YEAR = 2026;
    private const PAID_IN_AUGUST = [1, 2, 3];
    private const TO_CALCULATE_IN_SEPTEMBER = [5, 6, 7];
    private const DAYS = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi'];

    public function run(): void
    {
        $allIds = array_merge(self::PAID_IN_AUGUST, self::TO_CALCULATE_IN_SEPTEMBER);
        $teachers = Teacher::with('user')->whereIn('id', $allIds)->get()->keyBy('id');
        $validator = User::where('email', 'compta@sirh.local')->first() ?? User::role('admin')->first();

        DB::transaction(function () use ($teachers, $allIds, $validator) {
            Attendance::withTrashed()->where('notes', self::MARKER)->forceDelete();
            Payroll::withTrashed()->whereIn('teacher_id', $allIds)
                ->where('year', self::YEAR)->whereIn('month', [8, 9])
                ->get()->each->forceDelete();

            foreach ($teachers as $teacher) {
                if (in_array($teacher->id, self::PAID_IN_AUGUST)) {
                    $this->seedAttendances($teacher, Carbon::create(self::YEAR, 8, 1), Carbon::create(self::YEAR, 8, 31));
                }
                $this->seedAttendances($teacher, Carbon::create(self::YEAR, 9, 1), Carbon::create(self::YEAR, 9, 25));
            }

            foreach (self::PAID_IN_AUGUST as $id) {
                if ($teachers->has($id)) {
                    $this->seedPaidPayroll($teachers[$id], 8, $validator);
                }
            }
        });

        $this->command?->info('Démo honoraires : août payé pour ' . $this->names($teachers, self::PAID_IN_AUGUST)
            . ' ; septembre à calculer pour ' . $this->names($teachers, $allIds) . '.');
    }

    private function seedAttendances(Teacher $teacher, Carbon $from, Carbon $to): void
    {
        $schedulesByDay = Schedule::where('teacher_id', $teacher->id)
            ->where('status', 'scheduled')
            ->where(fn ($q) => $q->where('type', 'course')->orWhereNull('type'))
            ->orderBy('start_time')
            ->get()
            ->groupBy('day_of_week');

        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $schedule = $schedulesByDay->get(self::DAYS[$date->dayOfWeekIso] ?? '')?->first();
            if (!$schedule) {
                continue;
            }

            $alreadyClockedIn = Attendance::withTrashed()
                ->where('teacher_id', $teacher->id)
                ->whereDate('attendance_date', $date)
                ->exists();
            if ($alreadyClockedIn) {
                continue;
            }

            $start = Carbon::parse($schedule->start_time);
            $end = Carbon::parse($schedule->end_time);

            Attendance::create([
                'user_id' => $teacher->user_id,
                'teacher_id' => $teacher->id,
                'schedule_id' => $schedule->id,
                'attendance_date' => $date->toDateString(),
                'check_in_time' => $start->copy()->subMinutes(5)->format('H:i:s'),
                'check_out_time' => $end->format('H:i:s'),
                'status' => 'present',
                'attendance_mode' => 'physical_present',
                'hours_worked' => round($start->diffInMinutes($end) / 60, 2),
                'notes' => self::MARKER,
            ]);
        }
    }

    private function seedPaidPayroll(Teacher $teacher, int $month, ?User $validator): void
    {
        $attendances = Attendance::with('schedule')
            ->where('teacher_id', $teacher->id)
            ->whereYear('attendance_date', self::YEAR)
            ->whereMonth('attendance_date', $month)
            ->where('status', '!=', 'absent')
            ->get();

        $hours = round($attendances->sum(fn ($a) => $a->schedule
            ? Carbon::parse($a->schedule->start_time)->diffInMinutes(Carbon::parse($a->schedule->end_time)) / 60
            : (float) $a->hours_worked), 2);
        if ($hours <= 0) {
            return;
        }

        // Same rules as PayrollController::calculate().
        $rate = (float) $teacher->hourly_rate;
        $base = round($hours * $rate, 2);
        $brs = $base >= 25000 ? round($base * 0.05, 2) : 0;
        $net = $base - $brs;
        $method = $net >= 100000 ? 'check' : 'cash';
        $calculatedAt = Carbon::create(self::YEAR, $month + 1, 2, 10);

        $payroll = Payroll::create([
            'teacher_id' => $teacher->id,
            'year' => self::YEAR,
            'month' => $month,
            'base_salary' => 0,
            'honorarium_amount' => $base,
            'brs_amount' => $brs,
            'gross_salary' => $base,
            'deductions' => $brs,
            'net_salary' => $net,
            'payment_method' => $method,
            'status' => 'paid',
            'validated_by' => $validator?->id,
            'validated_at' => $calculatedAt->copy()->addDay(),
            'calculation_details' => [
                'type' => 'vacataire',
                'hours_attendance' => $hours,
                'hours_deducted_leaves' => 0,
                'hours_worked' => $hours,
                'hourly_rate' => $rate,
                'brs_applied' => $brs > 0,
                'brs_amount' => $brs,
                'manual_bonus' => 0,
                'manual_deduction' => 0,
                'payment_method' => $method,
                'incomplete_attendances' => 0,
                'approved_leaves' => ['total_days' => 0, 'details' => []],
            ],
        ]);
        $payroll->forceFill(['created_at' => $calculatedAt, 'updated_at' => $calculatedAt->copy()->addDays(3)])->saveQuietly();

        PayrollDetail::create([
            'payroll_id' => $payroll->id,
            'description' => 'Honoraire : ' . number_format($hours, 1) . 'h × ' . number_format($rate, 0, ',', ' ') . ' F',
            'type' => 'earning',
            'amount' => $base,
        ]);
        if ($brs > 0) {
            PayrollDetail::create([
                'payroll_id' => $payroll->id,
                'description' => 'BRS (5 % sur ' . number_format($base, 0, ',', ' ') . ' F)',
                'type' => 'deduction',
                'amount' => $brs,
            ]);
        }
    }

    private function names($teachers, array $ids): string
    {
        return collect($ids)->map(fn ($id) => $teachers[$id]->user->name ?? "#$id")->implode(', ');
    }
}
