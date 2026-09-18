<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Teacher;
use App\Models\Ufr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherTest extends TestCase {
    use RefreshDatabase;

    protected $adminHeaders;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $admin = User::where('email', 'admin@sirh.local')->first();
        $token = $admin->createToken('test')->plainTextToken;
        $this->adminHeaders = ['Authorization' => "Bearer $token"];
    }

    public function test_admin_can_list_teachers() {
        $response = $this->withHeaders($this->adminHeaders)
                         ->getJson('/api/teachers');

        $response->assertOk();
    }

    public function test_admin_can_create_teacher() {
        $dept = Ufr::first();

        $response = $this->withHeaders($this->adminHeaders)
                         ->postJson('/api/teachers', [
                             'name' => 'Test Teacher',
                             'email' => 'teacher@test.local',
                             'ufr_id' => $dept->id,
                             'employee_id' => 'TCH-TEST-001',
                             'hire_date' => '2025-01-01',
                             'base_salary' => 500000,
                             'contract_type' => 'CDI',
                             'password' => 'Changez-Moi-2026!',
                             'password_confirmation' => 'Changez-Moi-2026!',
                         ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', ['email' => 'teacher@test.local']);
        $this->assertDatabaseHas('teachers', ['employee_id' => 'TCH-TEST-001']);
    }

    public function test_admin_can_show_teacher() {
        $teacher = Teacher::first();

        $response = $this->withHeaders($this->adminHeaders)
                         ->getJson("/api/teachers/{$teacher->id}");

        $response->assertOk();
    }

    public function test_admin_can_update_teacher() {
        $teacher = Teacher::first();

        $response = $this->withHeaders($this->adminHeaders)
                         ->putJson("/api/teachers/{$teacher->id}", [
                             'base_salary' => 999999,
                         ]);

        $response->assertOk();
    }

    public function test_admin_can_archive_teacher() {
        $teacher = Teacher::first();

        $response = $this->withHeaders($this->adminHeaders)
                         ->patchJson("/api/teachers/{$teacher->id}/archive");

        $response->assertOk();
    }

    public function test_hr_manager_can_access_teachers() {
        $hr = User::where('email', 'rh@sirh.local')->first();
        $this->assertNotNull($hr, 'HR user not found in seeder');

        $token = $hr->createToken('test')->plainTextToken;
        $hrHeaders = ['Authorization' => "Bearer $token"];

        $response = $this->withHeaders($hrHeaders)
                         ->getJson('/api/teachers');

        $response->assertOk();
    }

    public function test_teacher_cannot_create_teacher() {
        $teacher_user = User::where('email', 'prof@sirh.local')->first();
        if (!$teacher_user) {
            $this->markTestSkipped('No teacher user found');
        }
        $token = $teacher_user->createToken('test')->plainTextToken;
        $teacherHeaders = ['Authorization' => "Bearer $token"];

        $dept = Ufr::first();

        $response = $this->withHeaders($teacherHeaders)
                         ->postJson('/api/teachers', [
                             'name' => 'Should Fail',
                             'email' => 'fail@test.local',
                             'ufr_id' => $dept->id,
                             'employee_id' => 'TCH-FAIL',
                             'hire_date' => '2025-01-01',
                             'base_salary' => 500000,
                             'password' => 'Changez-Moi-2026!',
                             'password_confirmation' => 'Changez-Moi-2026!',
                         ]);

        $response->assertStatus(403);
    }
}
