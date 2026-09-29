<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Ufr;
use App\Models\Teacher;
use App\Models\Payroll;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use RefreshDatabase;

    protected $adminHeaders;
    protected $accountantHeaders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\CompleteSeeder::class);

        $admin = User::where('email', 'admin@sirh.local')->first();
        $this->adminHeaders = ['Authorization' => 'Bearer ' . $admin->createToken('test')->plainTextToken];

        $compta = User::where('email', 'compta@sirh.local')->first();
        $this->accountantHeaders = ['Authorization' => 'Bearer ' . $compta->createToken('test')->plainTextToken];
    }

    public function test_calculate_honorarium_for_vacataire()
    {
        $teacher = Teacher::first();
        $teacher->update(['hourly_rate' => 5000]);

        $response = $this->withHeaders($this->accountantHeaders)
            ->postJson('/api/payroll/calculate', [
                'teacher_id' => $teacher->id,
                'month' => 3,
                'year' => 2026,
                'hours_worked' => 10,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('payroll.status', 'calculated');
    }

    public function test_all_teachers_are_paid_by_hour()
    {
        $teacher = Teacher::first();
        $teacher->update(['hourly_rate' => 5000]);

        $response = $this->withHeaders($this->accountantHeaders)
            ->postJson('/api/payroll/calculate', [
                'teacher_id' => $teacher->id,
                'month' => 3,
                'year' => 2026,
                'hours_worked' => 20,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('payroll.honorarium_amount', '100000.00');
    }

    public function test_brs_applied_when_above_threshold()
    {
        $teacher = Teacher::first();
        $teacher->update(['hourly_rate' => 10000]);

        $response = $this->withHeaders($this->accountantHeaders)
            ->postJson('/api/payroll/calculate', [
                'teacher_id' => $teacher->id,
                'month' => 3,
                'year' => 2026,
                'hours_worked' => 10,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('summary.brs_amount', 5000);
    }

    public function test_brs_not_applied_below_threshold()
    {
        $teacher = Teacher::first();
        $teacher->update(['hourly_rate' => 1000]);

        $response = $this->withHeaders($this->accountantHeaders)
            ->postJson('/api/payroll/calculate', [
                'teacher_id' => $teacher->id,
                'month' => 3,
                'year' => 2026,
                'hours_worked' => 10,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('summary.brs_amount', 0);
    }

    public function test_payment_by_check_when_above_100000()
    {
        $teacher = Teacher::first();
        $teacher->update(['hourly_rate' => 20000]);

        $response = $this->withHeaders($this->accountantHeaders)
            ->postJson('/api/payroll/calculate', [
                'teacher_id' => $teacher->id,
                'month' => 3,
                'year' => 2026,
                'hours_worked' => 50,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('summary.payment_method', 'check');
    }

    public function test_calculate_requires_hourly_rate()
    {
        $teacher = Teacher::first();
        $teacher->update(['hourly_rate' => 0]);

        $response = $this->withHeaders($this->accountantHeaders)
            ->postJson('/api/payroll/calculate', [
                'teacher_id' => $teacher->id,
                'month' => 3,
                'year' => 2026,
                'hours_worked' => 10,
            ]);

        $response->assertStatus(422);
    }

    public function test_calculate_rejects_missing_hours()
    {
        $teacher = Teacher::first();
        $teacher->update(['hourly_rate' => 5000]);

        $response = $this->withHeaders($this->accountantHeaders)
            ->postJson('/api/payroll/calculate', [
                'teacher_id' => $teacher->id,
                'month' => 3,
                'year' => 2026,
            ]);

        $response->assertStatus(422);
    }

    public function test_validate_payroll()
    {
        $teacher = Teacher::first();
        $teacher->update(['hourly_rate' => 5000]);

        $calc = $this->withHeaders($this->accountantHeaders)
            ->postJson('/api/payroll/calculate', [
                'teacher_id' => $teacher->id, 'month' => 3, 'year' => 2026, 'hours_worked' => 10,
            ]);
        $payrollId = $calc->json('payroll.id');

        $response = $this->withHeaders($this->accountantHeaders)
            ->postJson("/api/payroll/{$payrollId}/validate");

        $response->assertStatus(200);
        $this->assertDatabaseHas('payrolls', ['id' => $payrollId, 'status' => 'validated']);
    }

    public function test_mark_as_paid()
    {
        $teacher = Teacher::first();
        $teacher->update(['hourly_rate' => 5000]);

        $calc = $this->withHeaders($this->accountantHeaders)
            ->postJson('/api/payroll/calculate', [
                'teacher_id' => $teacher->id, 'month' => 3, 'year' => 2026, 'hours_worked' => 10,
            ]);
        $payrollId = $calc->json('payroll.id');

        $this->withHeaders($this->accountantHeaders)
            ->postJson("/api/payroll/{$payrollId}/validate");

        $response = $this->withHeaders($this->accountantHeaders)
            ->postJson("/api/payroll/{$payrollId}/mark-as-paid");

        $response->assertStatus(200);
        $this->assertDatabaseHas('payrolls', ['id' => $payrollId, 'status' => 'paid']);
    }

    public function test_export_csv()
    {
        $teacher = Teacher::first();
        $teacher->update(['hourly_rate' => 5000]);

        $this->withHeaders($this->accountantHeaders)
            ->postJson('/api/payroll/calculate', [
                'teacher_id' => $teacher->id, 'month' => 3, 'year' => 2026, 'hours_worked' => 10,
            ]);

        $response = $this->withHeaders($this->accountantHeaders)
            ->get('/api/payroll/export?month=3&year=2026');

        $response->assertStatus(200);
    }

    public function test_teacher_can_view_own_payroll()
    {
        $teacher = Teacher::first();
        if (!$teacher) {
            $this->markTestSkipped('No teachers available in seeded data');
            return;
        }

        $teacher->update(['hourly_rate' => 5000]);

        $this->withHeaders($this->accountantHeaders)
            ->postJson('/api/payroll/calculate', [
                'teacher_id' => $teacher->id, 'month' => 3, 'year' => 2026, 'hours_worked' => 10,
            ]);

        $teacherUser = $teacher->user;
        $token = $teacherUser->createToken('test')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->getJson('/api/payroll');

        $response->assertStatus(200);
    }

    public function test_unauthorized_cannot_calculate()
    {
        $teacher = Teacher::first();
        $teacherUser = $teacher->user;
        $token = $teacherUser->createToken('test')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => "Bearer $token"])
            ->postJson('/api/payroll/calculate', [
                'teacher_id' => $teacher->id, 'month' => 3, 'year' => 2026, 'hours_worked' => 10,
            ]);

        $response->assertStatus(403);
    }

    public function test_add_line_item()
    {
        $teacher = Teacher::first();
        $teacher->update(['hourly_rate' => 5000]);

        $calc = $this->withHeaders($this->accountantHeaders)
            ->postJson('/api/payroll/calculate', [
                'teacher_id' => $teacher->id, 'month' => 3, 'year' => 2026, 'hours_worked' => 10,
            ]);
        $payrollId = $calc->json('payroll.id');

        $response = $this->withHeaders($this->accountantHeaders)
            ->postJson("/api/payroll/{$payrollId}/line-item", [
                'description' => 'Prime exceptionnelle',
                'amount' => 15000,
                'type' => 'earning',
            ]);

        $response->assertStatus(200);
    }
}
