<?php
$file = 'app/Http/Controllers/Api/AttendanceController.php';
$content = file_get_contents($file);

// Replace 1: Add helper methods after getDayName
$search1 = "    private function getDayName(Carbon \$date): string
    {
        return ['', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'][\$date->dayOfWeekIso];
    }

    private function getCurrentScheduleForTeacher(Teacher \$teacher, Carbon \$now): ?Schedule";

$replace1 = "    private function getDayName(Carbon \$date): string
    {
        return ['', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'][\$date->dayOfWeekIso];
    }

    private function getSchedulesForToday(Teacher \$teacher)
    {
        \$dayName = \$this->getDayName(now());
        return Schedule::where('teacher_id', \$teacher->id)
            ->where('day_of_week', \$dayName)
            ->where('is_archived', false)
            ->orderBy('start_time')
            ->get();
    }

    private function findValidScheduleForCheckIn(\$schedules, Carbon \$now): ?Schedule
    {
        foreach (\$schedules as \$schedule) {
            \$start = Carbon::createFromTimeString(\$schedule->start_time);
            \$end = Carbon::createFromTimeString(\$schedule->end_time);
            \$allowedStart = \$start->copy()->subMinutes(self::CHECK_IN_EARLY_MINUTES);
            \$allowedEnd = \$end->copy()->addMinutes(self::LATE_THRESHOLD_MINUTES);
            if (\$now->gte(\$allowedStart) && \$now->lte(\$allowedEnd)) {
                return \$schedule;
            }
        }
        return null;
    }

    private function getCurrentScheduleForTeacher(Teacher \$teacher, Carbon \$now): ?Schedule";

if (strpos($content, $search1) === false) {
    die("Error: Could not find getDayName section\n");
}

$content = str_replace($search1, $replace1, $content);

// Replace 2: Update checkIn validation logic
$search2 = "        if (!\$pinValid) {
            return response()->json(['message' => 'Code PIN invalide'], 422);
        }

        \$now = now();
        \$today = Carbon::today();

        \$schedule = \$this->getCurrentScheduleForTeacher(\$teacher, \$now);

        if (!\$schedule) {
            return response()->json([
                'message' => \"Vous n'avez pas de cours en ce moment. Le pointage est uniquement autorisé pendant les heures de cours.\",
                'error' => 'no_schedule',
            ], 422);
        }";

$replace2 = "        if (!\$pinValid) {
            return response()->json(['message' => 'Code PIN invalide'], 422);
        }

        \$now = now();
        \$today = Carbon::today();

        \$schedulesToday = \$this->getSchedulesForToday(\$teacher);

        if (\$schedulesToday->isEmpty()) {
            return response()->json([
                'message' => 'Vous n\\'avez pas cours aujourd\\'hui',
                'error' => 'no_courses_today',
            ], 422);
        }

        \$validSchedule = \$this->findValidScheduleForCheckIn(\$schedulesToday, \$now);

        if (!\$validSchedule) {
            \$timeWindows = [];
            foreach (\$schedulesToday as \$sch) {
                \$start = Carbon::createFromTimeString(\$sch->start_time);
                \$end = Carbon::createFromTimeString(\$sch->end_time);
                \$allowedStart = \$start->copy()->subMinutes(self::CHECK_IN_EARLY_MINUTES);
                \$allowedEnd = \$end->copy()->addMinutes(self::LATE_THRESHOLD_MINUTES);
                \$timeWindows[] = \$allowedStart->format('H:i') . ' - ' . \$allowedEnd->format('H:i');
            }
            \$validTimes = implode(', ', \$timeWindows);
            return response()->json([
                'message' => 'Pointage possible entre: ' . \$validTimes,
                'error' => 'outside_time_window',
                'valid_times' => \$validTimes,
            ], 422);
        }

        \$schedule = \$validSchedule;";

if (strpos($content, $search2) === false) {
    die("Error: Could not find checkIn section\n");
}

$content = str_replace($search2, $replace2, $content);

file_put_contents($file, $content);
echo "✅ Attendance controller updated!\n";

// Verify
exec('php -l app/Http/Controllers/Api/AttendanceController.php 2>&1', $output, $code);
foreach ($output as $line) {
    echo $line . "\n";
}
exit($code);
?>
