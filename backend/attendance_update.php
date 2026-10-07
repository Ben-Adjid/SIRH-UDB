<?php
// Fragment 1: Add after getDayName
echo <<<'EOD'

    private function getSchedulesForToday(Teacher $teacher)
    {
        $dayName = $this->getDayName(now());
        return Schedule::where('teacher_id', $teacher->id)
            ->where('day_of_week', $dayName)
            ->where('is_archived', false)
            ->orderBy('start_time')
            ->get();
    }

    private function findValidScheduleForCheckIn($schedules, Carbon $now): ?Schedule
    {
        foreach ($schedules as $schedule) {
            $start = Carbon::createFromTimeString($schedule->start_time);
            $end = Carbon::createFromTimeString($schedule->end_time);
            $allowedStart = $start->copy()->subMinutes(self::CHECK_IN_EARLY_MINUTES);
            $allowedEnd = $end->copy()->addMinutes(self::LATE_THRESHOLD_MINUTES);
            if ($now->gte($allowedStart) && $now->lte($allowedEnd)) {
                return $schedule;
            }
        }
        return null;
    }
EOD;
?>