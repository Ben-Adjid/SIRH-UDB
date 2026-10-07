<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Attendance;
use Carbon\Carbon;

class AddSeptemberAttendanceSeeder extends Seeder
{
    public function run(): void
    {
        // Prof Amadou Diallo: 42 heures en septembre
        // Généralement 8-9 heures par jour travaillé
        $dates = [
            '2026-09-02', '2026-09-03', '2026-09-04', '2026-09-05',
            '2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11',
            '2026-09-15', '2026-09-16', '2026-09-17', '2026-09-18',
        ];

        // Prof Amadou Diallo (user_id: 6, teacher_id: 1)
        foreach ($dates as $idx => $date) {
            $hours = $idx < 4 ? 8 : ($idx < 8 ? 8.5 : 7);
            Attendance::firstOrCreate(
                [
                    'user_id' => 6,
                    'teacher_id' => 1,
                    'attendance_date' => $date,
                ],
                [
                    'check_in_time' => '08:00:00',
                    'check_out_time' => '16:30:00',
                    'status' => 'present',
                    'hours_worked' => $hours,
                ]
            );
        }

        // Aïcha Ba (user_id: 10, teacher_id: 5)
        foreach ($dates as $idx => $date) {
            $hours = $idx < 4 ? 8 : ($idx < 8 ? 8.5 : 7);
            Attendance::firstOrCreate(
                [
                    'user_id' => 10,
                    'teacher_id' => 5,
                    'attendance_date' => $date,
                ],
                [
                    'check_in_time' => '08:00:00',
                    'check_out_time' => '16:30:00',
                    'status' => 'present',
                    'hours_worked' => $hours,
                ]
            );
        }

        $this->command->info('✅ September attendance records added for Prof Amadou Diallo and Aïcha Ba');
    }
}
