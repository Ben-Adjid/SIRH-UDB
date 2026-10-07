<?php
$file = 'app/Http/Controllers/Api/AttendanceController.php';
$content = file_get_contents($file);

// Step 1: Add the two new helper methods after getDayName
$getDayName = "private function getDayName(Carbon \$date): string\n    {\n        return ['', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'][\$date->dayOfWeekIso];\n    }";

$newMethods = "private function getDayName(Carbon \$date): string
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
    }";

$content = str_replace($getDayName, $newMethods, $content);

// Step 2: Modify the checkIn validation logic
$oldValidation = "        if (!\$pinValid) {
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

$newValidation = "        if (!\$pinValid) {
            return response()->json(['message' => 'Code PIN invalide'], 422);
        }

        \$now = now();
        \$today = Carbon::today();

        \$schedulesToday = \$this->getSchedulesForToday(\$teacher);

        if (\$schedulesToday->isEmpty()) {
            return response()->json([
                'message' => 'Vous n\'avez pas cours aujourd\'hui',
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

$content = str_replace($oldValidation, $newValidation, $content);

file_put_contents($file, $content);
echo "Attendance controller updated!\n";

// Verify it compiles
exec('php -l app/Http/Controllers/Api/AttendanceController.php', $output, $code);
echo implode("\n", $output) . "\n";
exit($code);
?>
