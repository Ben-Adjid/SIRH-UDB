<?php

namespace Database\Seeders;

use App\Models\Teacher;
use App\Models\Subject;
use App\Models\Classroom;
use App\Models\AcademicYear;
use App\Models\TeachingType;
use App\Models\Schedule;
use Illuminate\Database\Seeder;

class ProfCourseSeeder extends Seeder
{
    public function run(): void
    {
        $profEmails = ['prof1@sirh.local', 'prof2@sirh.local', 'prof3@sirh.local', 'prof@sirh.local'];

        $teachers = Teacher::whereHas('user', fn ($q) => $q->whereIn('email', $profEmails))
            ->with('user')
            ->get();

        if ($teachers->isEmpty()) {
            $this->command->warn('Aucun compte prof trouvé. Lancez d\'abord TestUsersSeeder.');
            return;
        }

        $activeYear = AcademicYear::where('is_active', true)->first() ?? AcademicYear::first();
        $cm = TeachingType::where('code', 'CM')->first();
        $classrooms = Classroom::orderBy('id')->get();

        if ($classrooms->isEmpty()) {
            $this->command->warn('Aucune salle trouvée. Lancez d\'abord ScheduleSeeder.');
            return;
        }

        $plans = [
            'prof1@sirh.local' => ['subject' => 'Introduction au Droit', 'level' => 'L1', 'day' => 'Lundi',    'start' => '08:00', 'end' => '10:00'],
            'prof2@sirh.local' => ['subject' => 'Droit Constitutionnel',  'level' => 'L1', 'day' => 'Mardi',    'start' => '10:00', 'end' => '12:00'],
            'prof3@sirh.local' => ['subject' => 'Droit Civil',            'level' => 'L2', 'day' => 'Mercredi', 'start' => '14:00', 'end' => '16:00'],
            'prof@sirh.local'  => ['subject' => 'Droit des Affaires',     'level' => 'L3', 'day' => 'Jeudi',    'start' => '08:00', 'end' => '10:00'],
        ];

        $classroomPool = $classrooms->pluck('id')->all();
        $idx = 0;

        foreach ($teachers as $teacher) {
            $email = $teacher->user->email;

            if (Schedule::where('teacher_id', $teacher->id)->exists()) {
                $this->command->info("⏭️  $email a déjà des cours — ignoré.");
                continue;
            }

            $plan = $plans[$email] ?? $plans['prof@sirh.local'];
            $subject = Subject::where('name', $plan['subject'])->first() ?? Subject::first();
            $classroomId = $classroomPool[$idx % count($classroomPool)];
            $idx++;

            Schedule::create([
                'teacher_id'        => $teacher->id,
                'subject_id'        => $subject->id,
                'teaching_type_id'  => $cm?->id,
                'classroom_id'      => $classroomId,
                'academic_year_id'  => $activeYear?->id,
                'level'             => $plan['level'],
                'semester'          => 'S1',
                'start_date'        => '2025-10-01',
                'end_date'          => '2026-01-31',
                'day_of_week'       => $plan['day'],
                'start_time'        => $plan['start'],
                'end_time'          => $plan['end'],
                'status'            => 'scheduled',
                'type'              => 'course',
            ]);

            $this->command->info("✅ Cours assigné à $email : {$subject->name} ({$plan['day']} {$plan['start']}-{$plan['end']})");
        }
    }
}
