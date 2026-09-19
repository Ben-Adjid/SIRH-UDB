<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PayrollControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Teacher $teacher;
    protected User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountant = User::factory()->create();
        $this->accountant->assignRole('accountant');

        $this->teacher = Teacher::factory()->create([
            'hourly_rate' => 2500,
        ]);
    }

    public function test_calculate_payroll_with_valid_data()
    {
        $response = $this->actingAs($this->accountant)
            ->postJson('/api/payroll/calculate', [
                'teacher_id' => $this->teacher->id,
                'month' => 9,
                'year' => 2026,
                'hours_worked' => 40,
                'bonus' => 0,
                'deduction' => 0,
            ]);

        $response->assertStatus(200);
        $this->assertIsNumeric($response->json('payroll.net_salary'));
    }

    public function test_brs_applied_at_threshold()
    {
        // Test: BRS applies when base >= 25,000
        $response = $this->actingAs($this->accountant)
            ->postJson('/api/payroll/calculate', [
                'teacher_id' => $this->teacher->id,
                'month' => 9,
                'year' => 2026,
                'hours_worked' => 10, // 10 * 2500 = 25,000
                'bonus' => 0,
                'deduction' => 0,
            ]);

        $response->assertStatus(200);
        $brsAmount = $response->json('payroll.brs_amount');
        $this->assertEquals(1250, $brsAmount); // 25,000 * 5%
    }

    public function test_brs_not_applied_below_threshold()
    {
        // Test: BRS does NOT apply when base < 25,000
        $response = $this->actingAs($this->accountant)
            ->postJson('/api/payroll/calculate', [
                'teacher_id' => $this->teacher->id,
                'month' => 9,
                'year' => 2026,
                'hours_worked' => 9, // 9 * 2500 = 22,500
                'bonus' => 0,
                'deduction' => 0,
            ]);

        $response->assertStatus(200);
        $brsAmount = $response->json('payroll.brs_amount');
        $this->assertEquals(0, $brsAmount); // No BRS below 25,000
    }

    public function test_payment_method_check_threshold()
    {
        // Test: Payment method = 'check' when net >= 100,000
        $response = $this->actingAs($this->accountant)
            ->postJson('/api/payroll/calculate', [
                'teacher_id' => $this->teacher->id,
                'month' => 9,
                'year' => 2026,
                'hours_worked' => 50, // 50 * 2500 = 125,000 gross
                'bonus' => 0,
                'deduction' => 0,
            ]);

        $response->assertStatus(200);
        $paymentMethod = $response->json('payroll.payment_method');
        $this->assertEquals('check', $paymentMethod);
    }

    public function test_payment_method_cash_threshold()
    {
        // Test: Payment method = 'cash' when net < 100,000
        $response = $this->actingAs($this->accountant)
            ->postJson('/api/payroll/calculate', [
                'teacher_id' => $this->teacher->id,
                'month' => 9,
                'year' => 2026,
                'hours_worked' => 30, // 30 * 2500 = 75,000 gross
                'bonus' => 0,
                'deduction' => 0,
            ]);

        $response->assertStatus(200);
        $paymentMethod = $response->json('payroll.payment_method');
        $this->assertEquals('cash', $paymentMethod);
    }

    public function test_unauthorized_user_cannot_calculate()
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('teacher');

        $response = $this->actingAs($teacher)
            ->postJson('/api/payroll/calculate', [
                'teacher_id' => $this->teacher->id,
                'month' => 9,
                'year' => 2026,
            ]);

        $response->assertStatus(403);
    }

    public function test_no_hours_returns_error()
    {
        $response = $this->actingAs($this->accountant)
            ->postJson('/api/payroll/calculate', [
                'teacher_id' => $this->teacher->id,
                'month' => 9,
                'year' => 2026,
                'hours_worked' => 0,
            ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('message', $response->json());
    }
}
