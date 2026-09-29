<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Homework;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeworkWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\CompleteSeeder::class);
    }

    private function token(string $email): string
    {
        return User::where('email', $email)->firstOrFail()->createToken('test')->plainTextToken;
    }

    private function createHomework(): Homework
    {
        $teacher = User::where('email', 'diop@sirh.local')->firstOrFail()->teacher;
        Schedule::firstOrCreate([
            'teacher_id' => $teacher->id,
            'subject_id' => Subject::firstOrFail()->id,
            'classroom_id' => Classroom::firstOrFail()->id,
            'start_date' => '2026-08-01',
            'day_of_week' => 'Lundi',
            'start_time' => '08:00',
            'end_time' => '10:00',
        ], [
            'level' => 'L1',
            'semester' => 'S1',
            'status' => 'scheduled',
            'is_archived' => false,
        ]);

        $response = $this->flushHeaders()->withToken($this->token('diop@sirh.local'))->postJson('/api/homeworks', [
            'subject_id' => Subject::firstOrFail()->id,
            'classroom_id' => Classroom::firstOrFail()->id,
            'title' => 'Devoir de contrôle',
            'description' => 'Travail à remettre.',
            'scheduled_date' => '2026-08-10',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'max_score' => 20,
        ]);

        $response->assertCreated();
        return Homework::findOrFail($response->json('homework.id'));
    }

    public function test_teacher_submission_is_pending_and_notifies_academic_manager(): void
    {
        $homework = $this->createHomework();

        $this->assertSame('planned', $homework->status);
        $academic = User::where('email', 'academic@sirh.local')->firstOrFail();
        $this->assertDatabaseHas('notifications', [
            'user_id' => $academic->id,
            'title' => 'Nouveau devoir créé',
        ]);
    }

    public function test_only_academic_manager_or_admin_can_approve(): void
    {
        $homework = $this->createHomework();

        $this->actingAs(User::where('email', 'rh@sirh.local')->firstOrFail(), 'sanctum')
            ->patchJson("/api/homeworks/{$homework->id}/approve")
            ->assertForbidden();

        $response = $this->actingAs(User::where('email', 'academic@sirh.local')->firstOrFail(), 'sanctum')
            ->patchJson("/api/homeworks/{$homework->id}/approve");

        $response->assertOk();
        $this->assertDatabaseHas('homeworks', ['id' => $homework->id, 'status' => 'approved']);

        $teacher = User::where('email', 'diop@sirh.local')->firstOrFail();
        $this->assertDatabaseHas('notifications', [
            'user_id' => $teacher->id,
            'title' => 'Devoir approuvé',
        ]);
    }

    public function test_teacher_cannot_enter_grades_before_approval(): void
    {
        $homework = $this->createHomework();

        $this->flushHeaders()->withToken($this->token('diop@sirh.local'))
            ->postJson("/api/homeworks/{$homework->id}/grades", [
                'grades' => [['student_id' => 'ST001', 'student_name' => 'Etudiant Test', 'score' => 15]],
            ])
            ->assertUnprocessable();
    }

    public function test_teacher_can_only_schedule_an_assigned_subject(): void
    {
        $teacher = User::where('email', 'diop@sirh.local')->firstOrFail()->teacher;
        $assignedSubject = Subject::firstOrFail();
        $unassignedSubject = Subject::whereKeyNot($assignedSubject->id)->firstOrFail();

        Schedule::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $assignedSubject->id,
            'classroom_id' => Classroom::firstOrFail()->id,
            'start_date' => '2026-08-01',
            'day_of_week' => 'Lundi',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'level' => 'L1',
            'semester' => 'S1',
            'status' => 'scheduled',
            'is_archived' => false,
        ]);

        $this->flushHeaders()->withToken($this->token('diop@sirh.local'))
            ->postJson('/api/homeworks', [
                'subject_id' => $unassignedSubject->id,
                'classroom_id' => Classroom::firstOrFail()->id,
                'title' => 'Matière non affectée',
                'description' => 'Ne doit pas être accepté.',
                'scheduled_date' => '2026-08-10',
                'start_time' => '08:00',
                'end_time' => '10:00',
                'max_score' => 20,
            ])
            ->assertUnprocessable();
    }

    public function test_teacher_cannot_modify_or_delete_approved_homework(): void
    {
        $homework = $this->createHomework();

        $this->actingAs(User::where('email', 'academic@sirh.local')->firstOrFail(), 'sanctum')
            ->patchJson("/api/homeworks/{$homework->id}/approve")
            ->assertOk();

        $teacher = User::where('email', 'diop@sirh.local')->firstOrFail();
        $this->actingAs($teacher, 'sanctum')
            ->putJson("/api/homeworks/{$homework->id}", ['title' => 'Modification interdite'])
            ->assertUnprocessable();

        $this->actingAs($teacher, 'sanctum')
            ->deleteJson("/api/homeworks/{$homework->id}")
            ->assertUnprocessable();
    }

    public function test_academic_manager_can_reschedule_then_close_homework(): void
    {
        $homework = $this->createHomework();
        $manager = User::where('email', 'academic@sirh.local')->firstOrFail();

        $this->actingAs($manager, 'sanctum')
            ->patchJson("/api/homeworks/{$homework->id}/approve")
            ->assertOk();

        $this->actingAs($manager, 'sanctum')
            ->patchJson("/api/homeworks/{$homework->id}/reschedule", [
                'scheduled_date' => now()->toDateString(),
                'start_time' => '14:00',
                'end_time' => '16:00',
            ])
            ->assertOk();

        $this->actingAs($manager, 'sanctum')
            ->patchJson("/api/homeworks/{$homework->id}/complete")
            ->assertOk();

        $this->assertDatabaseHas('homeworks', ['id' => $homework->id, 'status' => 'completed']);
    }
}
