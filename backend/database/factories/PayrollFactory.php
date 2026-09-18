<?php

namespace Database\Factories;

use App\Models\Payroll;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

class PayrollFactory extends Factory
{
    protected $model = Payroll::class;

    public function definition(): array
    {
        $base = fake()->numberBetween(50000, 500000);
        $brs = $base >= 25000 ? round($base * 0.05, 2) : 0;
        $net = $base - $brs;
        return [
            'teacher_id' => Teacher::factory(),
            'year' => fake()->numberBetween(2024, 2026),
            'month' => fake()->numberBetween(1, 12),
            'base_salary' => fake()->randomElement([0, $base]),
            'honorarium_amount' => $base,
            'brs_amount' => $brs,
            'gross_salary' => $base - $brs,
            'deductions' => $brs,
            'net_salary' => $net,
            'payment_method' => $net >= 100000 ? 'check' : 'cash',
            'status' => fake()->randomElement(['calculated', 'validated', 'paid']),
        ];
    }

    public function paid(): static {
        return $this->state(fn() => ['status' => 'paid']);
    }
}
