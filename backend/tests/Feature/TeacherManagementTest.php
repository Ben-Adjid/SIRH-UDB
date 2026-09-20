<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Teacher;
use App\Models\Ufr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $adminHeaders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\CompleteSeeder::class);

        $admin = User::where('email', 'admin@sirh.local')->first();
        $token = $admin->createToken('test')->plainTextToken;
        $this->adminHeaders = ['Authorization' => "Bearer $token"];
    }

    public function test_create_a_teacher_via_api()
    {
        $dept = Ufr::first();

        $response = $this->withHeaders($this->adminHeaders)
            ->postJson('/api/teachers', [
                'name' => 'Nouvel Enseignant',
                'email' => 'nouveau@sirh.local',
                'ufr_id' => $dept->id,
                'employee_id' => 'EMP-CREATE-001',
                'hire_date' => '2026-01-01',
                'hourly_rate' => 5000,
                'volume_hours' => 250,
                'base_salary' => 400000,
                'contract_type' => 'vacataire',
                'password' => 'Changez-Moi-2026!',
                'password_confirmation' => 'Changez-Moi-2026!',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'nouveau@sirh.local']);
        $this->assertDatabaseHas('teachers', ['employee_id' => 'EMP-CREATE-001']);
    }

    public function test_list_teachers_with_pagination()
    {
        $response = $this->withHeaders($this->adminHeaders)
            ->getJson('/api/teachers?per_page=2');

        $response->assertOk();
        $json = $response->json();
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('per_page', $json);
        $this->assertLessThanOrEqual(2, count($json['data']));
    }

    public function test_show_a_teacher()
    {
        $teacher = Teacher::first();

        $response = $this->withHeaders($this->adminHeaders)
            ->getJson("/api/teachers/{$teacher->id}");

        $response->assertOk();
        $response->assertJsonPath('id', $teacher->id);
    }

    public function test_update_a_teacher()
    {
        $teacher = Teacher::first();
        $originalName = $teacher->user->name;

        $response = $this->withHeaders($this->adminHeaders)
            ->putJson("/api/teachers/{$teacher->id}", [
                'name' => 'Nom Modifié',
                'hourly_rate' => 7500,
                'volume_hours' => 400,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('teachers', [
            'id' => $teacher->id,
            'hourly_rate' => '7500.00',
            'volume_hours' => 400,
        ]);
    }

    public function test_archive_and_activate_teacher()
    {
        $teacher = Teacher::first();

        $archiveResponse = $this->withHeaders($this->adminHeaders)
            ->patchJson("/api/teachers/{$teacher->id}/archive");

        $archiveResponse->assertOk();
        $this->assertDatabaseHas('teachers', [
            'id' => $teacher->id,
            'status' => 'inactive',
        ]);

        $activateResponse = $this->withHeaders($this->adminHeaders)
            ->patchJson("/api/teachers/{$teacher->id}/archive");

        $activateResponse->assertOk();
        $this->assertDatabaseHas('teachers', [
            'id' => $teacher->id,
            'status' => 'active',
        ]);
    }

    public function test_delete_a_teacher()
    {
        $teacher = Teacher::first();
        $teacherId = $teacher->id;
        $userId = $teacher->user_id;

        $response = $this->withHeaders($this->adminHeaders)
            ->deleteJson("/api/teachers/{$teacher->id}");

        $response->assertOk();
        $this->assertSoftDeleted('teachers', ['id' => $teacherId]);
        $this->assertSoftDeleted('users', ['id' => $userId]);
    }
}
