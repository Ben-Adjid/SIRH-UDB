<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Teacher;
use App\Models\Ufr;
use App\Models\Subject;
use App\Models\Classroom;
use App\Models\AcademicYear;
use App\Models\TeachingType;
use App\Models\Schedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UfrTeacherSeeder extends Seeder
{
    public function run(): void
    {
        $teachers = [
            ['email' => 'prof-gge@sirh.local', 'name' => 'Prof GGE Test',   'ufr' => 'UFR-GGE', 'subject' => 'Économie Générale',        'level' => 'L1', 'day' => 'Mardi',    'start' => '08:00', 'end' => '10:00', 'pin' => '2032'],
            ['email' => 'prof-st@sirh.local',  'name' => 'Prof ST Test',    'ufr' => 'UFR-ST',  'subject' => 'Mathématiques Générales', 'level' => 'L1', 'day' => 'Mercredi', 'start' => '10:00', 'end' => '12:00', 'pin' => '2033'],
            ['email' => 'prof-la@sirh.local',  'name' => 'Prof LA Test',    'ufr' => 'UFR-LA',  'subject' => 'Anglais Général',         'level' => 'L1', 'day' => 'Jeudi',    'start' => '14:00', 'end' => '16:00', 'pin' => '2034'],
        ];

        $password = 'password';
        $activeYear = AcademicYear::where('is_active', true)->first() ?? AcademicYear::first();
        $cm = TeachingType::where('code', 'CM')->first();
        $classroom = Classroom::orderBy('id')->first();

        foreach ($teachers as $data) {
            $ufr = Ufr::where('code', $data['ufr'])->first();
            if (!$ufr) {
                $this->command->warn("UFR {$data['ufr']} introuvable, ignoré.");
                continue;
            }

            $user = User::withTrashed()->where('email', $data['email'])->first();
            if (!$user) {
                $user = User::create([
                    'name'                => $data['name'],
                    'email'               => $data['email'],
                    'password'            => Hash::make($password),
                    'status'              => 'active',
                    'email_verified_at'   => now(),
                    'must_change_password' => false,
                ]);
            } else {
                $user->restore();
                $user->update([
                    'name'                => $data['name'],
                    'password'            => Hash::make($password),
                    'status'              => 'active',
                    'must_change_password' => false,
                ]);
            }
            $user->syncRoles(['teacher']);

            $teacher = Teacher::withTrashed()->where('user_id', $user->id)->first();
            if (!$teacher) {
                $teacher = Teacher::create([
                    'user_id'      => $user->id,
                    'ufr_id'       => $ufr->id,
                    'employee_id'  => 'ENS-' . strtoupper($data['ufr']),
                    'pin_code'     => $data['pin'],
                    'hire_date'    => now(),
                    'volume_hours' => 200,
                    'hourly_rate'  => 5000,
                    'status'       => 'active',
                ]);
            } else {
                $teacher->restore();
                $teacher->update([
                    'ufr_id'   => $ufr->id,
                    'pin_code' => $data['pin'],
                    'status'   => 'active',
                ]);
            }

            if (!Schedule::where('teacher_id', $teacher->id)->exists()) {
                $subject = Subject::where('name', $data['subject'])->first() ?? Subject::first();
                Schedule::create([
                    'teacher_id'       => $teacher->id,
                    'subject_id'       => $subject->id,
                    'teaching_type_id' => $cm?->id,
                    'classroom_id'     => $classroom->id,
                    'academic_year_id' => $activeYear?->id,
                    'level'            => $data['level'],
                    'semester'         => 'S1',
                    'start_date'       => '2025-10-01',
                    'end_date'         => '2026-01-31',
                    'day_of_week'      => $data['day'],
                    'start_time'       => $data['start'],
                    'end_time'         => $data['end'],
                    'status'           => 'scheduled',
                    'type'             => 'course',
                ]);
            }

            $this->command->info("✅ {$data['email']} (PIN {$data['pin']}) → {$ufr->name}");
        }

        $this->command->info("Mot de passe : {$password}");
    }
}
