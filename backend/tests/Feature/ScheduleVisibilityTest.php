<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Attendance;
use App\Models\Homework;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Ufr;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_ufr_manager_only_sees_schedules_of_owned_ufr(): void
    {
        $this->seed(\Database\Seeders\CompleteSeeder::class);

        $manager = User::where('email', 'academic@sirh.local')->firstOrFail();
        $ownedUfr = Ufr::where('code', 'UFR-ST')->firstOrFail();
        $otherUfr = Ufr::whereKeyNot($ownedUfr->id)->whereHas('teachers')->firstOrFail();
        $ownedUfr->update(['head_id' => $manager->id]);

        $ownedTeacher = Teacher::where('ufr_id', $ownedUfr->id)->firstOrFail();
        $otherTeacher = Teacher::where('ufr_id', $otherUfr->id)->firstOrFail();
        $subject = Subject::firstOrFail();
        $otherSubject = Subject::whereKeyNot($subject->id)->firstOrFail();
        $classroom = Classroom::firstOrFail();
        $attendanceDate = now()->toDateString();

        foreach ([[$ownedTeacher, $subject], [$otherTeacher, $otherSubject]] as [$teacher, $teacherSubject]) {
            Schedule::create([
                'teacher_id' => $teacher->id,
                'subject_id' => $teacherSubject->id,
                'classroom_id' => $classroom->id,
                'start_date' => '2026-08-01',
                'day_of_week' => 'Lundi',
                'start_time' => '08:00',
                'end_time' => '10:00',
                'level' => 'L1',
                'semester' => 'S1',
                'status' => 'scheduled',
                'is_archived' => false,
            ]);

            Homework::create([
                'teacher_id' => $teacher->id,
                'subject_id' => $teacherSubject->id,
                'classroom_id' => $classroom->id,
                'title' => 'Devoir UFR ' . $teacher->id,
                'description' => 'Devoir de test',
                'scheduled_date' => '2026-08-10',
                'start_time' => '08:00',
                'end_time' => '10:00',
                'duration_minutes' => 120,
                'status' => 'planned',
                'max_score' => 20,
            ]);

            Attendance::create([
                'user_id' => $teacher->user_id,
                'teacher_id' => $teacher->id,
                'attendance_date' => $attendanceDate,
                'status' => 'present',
            ]);
        }

        $response = $this->actingAs($manager, 'sanctum')->getJson('/api/schedules?per_page=50');

        $response->assertOk();
        $teacherIds = collect($response->json('data'))->pluck('teacher_id')->all();
        $this->assertContains($ownedTeacher->id, $teacherIds);
        $this->assertNotContains($otherTeacher->id, $teacherIds);

        $teacherResponse = $this->actingAs($manager, 'sanctum')->getJson('/api/teachers?per_page=50');
        $teacherResponse->assertOk();
        $visibleTeacherIds = collect($teacherResponse->json('data'))->pluck('id')->all();
        $this->assertContains($ownedTeacher->id, $visibleTeacherIds);
        $this->assertNotContains($otherTeacher->id, $visibleTeacherIds);

        $homeworkResponse = $this->actingAs($manager, 'sanctum')->getJson('/api/homeworks?per_page=50');
        $homeworkResponse->assertOk();
        $visibleHomeworkTeacherIds = collect($homeworkResponse->json('data'))->pluck('teacher_id')->all();
        $this->assertContains($ownedTeacher->id, $visibleHomeworkTeacherIds);
        $this->assertNotContains($otherTeacher->id, $visibleHomeworkTeacherIds);

        $subjectResponse = $this->actingAs($manager, 'sanctum')->getJson('/api/subjects?per_page=50');
        $subjectResponse->assertOk();
        $visibleSubjectIds = collect($subjectResponse->json('data'))->pluck('id')->all();
        $this->assertContains($subject->id, $visibleSubjectIds);
        $this->assertNotContains($otherSubject->id, $visibleSubjectIds);

        $attendanceResponse = $this->actingAs($manager, 'sanctum')->getJson('/api/attendance?date=' . $attendanceDate . '&per_page=50');
        $attendanceResponse->assertOk();
        $visibleAttendanceUserIds = collect($attendanceResponse->json('data'))->pluck('user_id')->all();
        $this->assertContains($ownedTeacher->user_id, $visibleAttendanceUserIds);
        $this->assertNotContains($otherTeacher->user_id, $visibleAttendanceUserIds);

        $ufrResponse = $this->actingAs($manager, 'sanctum')->getJson('/api/ufrs?per_page=50');
        $ufrResponse->assertOk();
        $this->assertCount(1, $ufrResponse->json('data'));
        $this->assertSame($ownedUfr->id, $ufrResponse->json('data.0.id'));
    }
}
