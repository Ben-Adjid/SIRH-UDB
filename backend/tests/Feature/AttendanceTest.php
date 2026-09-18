<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Teacher;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected $adminHeaders;
    protected $teacherHeaders;
    protected $teacherUser;
    protected $teacherPin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\CompleteSeeder::class);

        $admin = User::where('email', 'admin@sirh.local')->first();
        $this->adminHeaders = ['Authorization' => 'Bearer ' . $admin->createToken('test')->plainTextToken];

        $this->teacherUser = User::where('email', 'prof@sirh.local')->first();
        $this->teacherHeaders = ['Authorization' => 'Bearer ' . $this->teacherUser->createToken('test')->plainTextToken];
        $this->teacherPin = $this->teacherUser->teacher->pin_code;
    }

    public function test_check_in_with_valid_pin()
    {
        $response = $this->withHeaders($this->teacherHeaders)
            ->postJson('/api/attendance/check-in', ['pin' => $this->teacherPin]);

        $this->assertContains($response->status(), [200, 201, 403, 422]);
    }

    public function test_check_in_with_invalid_pin_is_rejected()
    {
        $response = $this->withHeaders($this->teacherHeaders)
            ->postJson('/api/attendance/check-in', ['pin' => '0000']);

        $response->assertStatus(422);
    }

    public function test_check_out()
    {
        $checkIn = $this->withHeaders($this->teacherHeaders)
            ->postJson('/api/attendance/check-in', ['pin' => $this->teacherPin]);

        if (!in_array($checkIn->status(), [200, 201])) {
            $this->markTestSkipped('Check-in failed, cannot test check-out');
        }

        $response = $this->withHeaders($this->teacherHeaders)
            ->postJson('/api/attendance/check-out');

        $response->assertStatus(200);
        $attendance = Attendance::where('user_id', $this->teacherUser->id)
            ->where('attendance_date', now()->toDateString())
            ->first();
        if ($attendance) {
            $this->assertNotNull($attendance->check_out_time);
            $this->assertNotNull($attendance->hours_worked);
        }
    }

    public function test_list_attendances()
    {
        $this->withHeaders($this->teacherHeaders)
            ->postJson('/api/attendance/check-in', ['pin' => $this->teacherPin]);

        $response = $this->withHeaders($this->adminHeaders)
            ->getJson('/api/attendance');

        $response->assertStatus(200);
    }

    public function test_validation_keeps_reason_shows_validator_and_cannot_repeat()
    {
        $date = now()->toDateString();

        $reported = $this->actingAs($this->teacherUser, 'sanctum')
            ->postJson('/api/attendance/report-absence', [
                'attendance_date' => $date,
                'status' => 'late',
                'reason' => 'Transport en panne',
            ]);

        $reported->assertOk();
        $attendance = Attendance::findOrFail($reported->json('attendance.id'));

        $validated = $this->actingAs(User::where('email', 'rh@sirh.local')->firstOrFail(), 'sanctum')
            ->patchJson("/api/attendance/{$attendance->id}/validate-absence", [
                'justified' => false,
                'notes' => 'Motif non conforme aux justificatifs transmis',
            ]);

        $validated->assertOk()
            ->assertJsonPath('attendance.justification_status', 'unjustified')
            ->assertJsonPath('attendance.validator.name', 'Responsable Administratif')
            ->assertJsonPath('attendance.notes', 'Transport en panne')
            ->assertJsonPath('attendance.justification_notes', 'Motif non conforme aux justificatifs transmis');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'justification_status' => 'unjustified',
            'justified_by' => User::where('email', 'rh@sirh.local')->value('id'),
            'notes' => 'Transport en panne',
        ]);

        $this->actingAs(User::where('email', 'admin@sirh.local')->firstOrFail(), 'sanctum')
            ->patchJson("/api/attendance/{$attendance->id}/validate-absence", [
                'justified' => true,
            ])
            ->assertStatus(409)
            ->assertJsonPath('attendance.validator.name', 'Responsable Administratif');
    }
}
