<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Teacher;
use App\Models\Payroll;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollCalculationTest extends TestCase
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

    public function test_calculate_honorarium_for_teacher_with_valid_hours()
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
        $response->assertJsonPath('payroll.honorarium_amount', '50000.00');
        $response->assertJsonPath('summary.base_amount', 50000);
    }

    public function test_error_when_teacher_has_no_hourly_rate()
    {
        $teacher = Teacher::first();
        $teacher->update(['hourly_rate' => 0]);

        $response = $this->withHeaders($this->accountantHeaders)
            ->postJson('/api/payroll/calculate', [
                'teacher_id' => $teacher->id,
                'month' => 4,
                'year' => 2026,
                'hours_worked' => 10,
            ]);

        $response->assertStatus(422);
    }

    public function test_brs_applied_when_honorarium_above_25000()
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
        $this->assertTrue($response->json('summary.brs_amount') > 0);
        $this->assertEquals(2500, $response->json('summary.brs_amount'));
    }

    public function test_brs_not_applied_when_honorarium_below_25000()
    {
        $teacher = Teacher::first();
        $teacher->update(['hourly_rate' => 1000]);

        $response = $this->withHeaders($this->accountantHeaders)
            ->postJson('/api/payroll/calculate', [
                'teacher_id' => $teacher->id,
                'month' => 4,
                'year' => 2026,
                'hours_worked' => 10,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('summary.brs_amount', 0);
    }

    public function test_payment_method_is_check_when_net_above_100000()
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

    public function test_payment_method_is_cash_when_net_below_100000()
    {
        $teacher = Teacher::first();
        $teacher->update(['hourly_rate' => 2000]);

        $response = $this->withHeaders($this->accountantHeaders)
            ->postJson('/api/payroll/calculate', [
                'teacher_id' => $teacher->id,
                'month' => 4,
                'year' => 2026,
                'hours_worked' => 10,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('summary.payment_method', 'cash');
    }

    public function test_list_payrolls_with_pagination()
    {
        $teacher = Teacher::first();
        $teacher->update(['hourly_rate' => 5000]);

        $this->withHeaders($this->accountantHeaders)
            ->postJson('/api/payroll/calculate', [
                'teacher_id' => $teacher->id,
                'month' => 3,
                'year' => 2026,
                'hours_worked' => 10,
            ]);

        $this->withHeaders($this->accountantHeaders)
            ->postJson('/api/payroll/calculate', [
                'teacher_id' => $teacher->id,
                'month' => 4,
                'year' => 2026,
                'hours_worked' => 20,
            ]);

        $response = $this->withHeaders($this->accountantHeaders)
            ->getJson('/api/payroll?per_page=10');

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('per_page', $json);
        $this->assertCount(2, $json['data']);
    }

    public function test_validate_payroll()
    {
        $teacher = Teacher::first();
        $teacher->update(['hourly_rate' => 5000]);

        $calc = $this->withHeaders($this->accountantHeaders)
            ->postJson('/api/payroll/calculate', [
                'teacher_id' => $teacher->id,
                'month' => 3,
                'year' => 2026,
                'hours_worked' => 10,
            ]);
        $payrollId = $calc->json('payroll.id');

        $response = $this->withHeaders($this->accountantHeaders)
            ->postJson("/api/payroll/{$payrollId}/validate");

        $response->assertStatus(200);
        $this->assertDatabaseHas('payrolls', [
            'id' => $payrollId,
            'status' => 'validated',
        ]);
    }

    public function test_mark_as_paid_only_from_validated()
    {
        $teacher = Teacher::first();
        $teacher->update(['hourly_rate' => 5000]);

        $calc = $this->withHeaders($this->accountantHeaders)
            ->postJson('/api/payroll/calculate', [
                'teacher_id' => $teacher->id,
                'month' => 6,
                'year' => 2026,
                'hours_worked' => 10,
            ]);
        $payrollId = $calc->json('payroll.id');

        $failResponse = $this->withHeaders($this->accountantHeaders)
            ->postJson("/api/payroll/{$payrollId}/mark-as-paid");
        $failResponse->assertStatus(422);

        $this->withHeaders($this->accountantHeaders)
            ->postJson("/api/payroll/{$payrollId}/validate");

        $response = $this->withHeaders($this->accountantHeaders)
            ->postJson("/api/payroll/{$payrollId}/mark-as-paid");

        $response->assertStatus(200);
        $this->assertDatabaseHas('payrolls', [
            'id' => $payrollId,
            'status' => 'paid',
        ]);
    }

    public function test_export_csv()
    {
        $teacher = Teacher::first();
        $teacher->update(['hourly_rate' => 5000]);

        $this->withHeaders($this->accountantHeaders)
            ->postJson('/api/payroll/calculate', [
                'teacher_id' => $teacher->id,
                'month' => 3,
                'year' => 2026,
                'hours_worked' => 10,
            ]);

        $response = $this->withHeaders($this->accountantHeaders)
            ->get('/api/payroll/export?month=3&year=2026');

        $response->assertStatus(200);
    }
}
