<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Ufr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UfrTest extends TestCase {
    use RefreshDatabase;

    protected $adminHeaders;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $admin = User::where('email', 'admin@sirh.local')->first();
        $token = $admin->createToken('test')->plainTextToken;
        $this->adminHeaders = ['Authorization' => "Bearer $token"];
    }

    public function test_admin_can_list_ufrs() {
        $response = $this->withHeaders($this->adminHeaders)
                         ->getJson('/api/ufrs');

        $response->assertOk()
                 ->assertJsonStructure(['data' => [['id', 'name', 'code']]]);
    }

    public function test_admin_can_create_department() {
        $response = $this->withHeaders($this->adminHeaders)
                         ->postJson('/api/ufrs', [
                             'name' => 'Mathematics',
                             'code' => 'MATH',
                             'description' => 'Math department',
                         ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('ufrs', ['code' => 'MATH']);
    }

    public function test_create_department_requires_unique_code() {
        Ufr::create(['name' => 'Existing', 'code' => 'EXIST']);

        $response = $this->withHeaders($this->adminHeaders)
                         ->postJson('/api/ufrs', [
                             'name' => 'Duplicate',
                             'code' => 'EXIST',
                         ]);

        $response->assertStatus(422);
    }

    public function test_admin_can_show_department() {
        $dept = Ufr::first();

        $response = $this->withHeaders($this->adminHeaders)
                         ->getJson("/api/ufrs/{$dept->id}");

        $response->assertOk()
                 ->assertJsonPath('data.id', $dept->id);
    }

    public function test_show_nonexistent_department_returns_404() {
        $response = $this->withHeaders($this->adminHeaders)
                         ->getJson('/api/ufrs/99999');

        $response->assertStatus(404);
    }

    public function test_admin_can_update_department() {
        $dept = Ufr::first();

        $response = $this->withHeaders($this->adminHeaders)
                         ->putJson("/api/ufrs/{$dept->id}", [
                             'name' => 'Updated Dept',
                         ]);

        $response->assertOk();
        $this->assertDatabaseHas('ufrs', ['id' => $dept->id, 'name' => 'Updated Dept']);
    }

    public function test_admin_can_delete_empty_department() {
        $dept = Ufr::create(['name' => 'Empty Dept', 'code' => 'EMPTY']);

        $response = $this->withHeaders($this->adminHeaders)
                         ->deleteJson("/api/ufrs/{$dept->id}");

        $response->assertOk();
        $this->assertSoftDeleted('ufrs', ['id' => $dept->id]);
    }

    public function test_cannot_delete_ufr_with_teachers() {
        $dept = Ufr::first();
        $user = User::factory()->create();
        $user->assignRole('teacher');
        \App\Models\Teacher::create([
            'user_id' => $user->id,
            'ufr_id' => $dept->id,
            'employee_id' => 'TST001',
            'contract_type' => 'vacataire',
            'hourly_rate' => 5000,
            'volume_hours' => 0,
            'hire_date' => now(),
            'status' => 'active',
        ]);

        $response = $this->withHeaders($this->adminHeaders)
                         ->deleteJson("/api/ufrs/{$dept->id}");

        $response->assertStatus(422);
    }

    public function test_unauthenticated_cannot_access_ufrs() {
        $response = $this->getJson('/api/ufrs');
        $response->assertStatus(401);
    }
}
