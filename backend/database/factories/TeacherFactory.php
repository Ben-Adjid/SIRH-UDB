<?php

namespace Database\Factories;

use App\Models\Ufr;
use App\Models\User;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeacherFactory extends Factory
{
    protected $model = Teacher::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ufr_id' => Ufr::factory(),
            'employee_id' => 'ENS-' . fake()->unique()->numberBetween(10000, 99999),
            'hire_date' => fake()->date(),
            'volume_hours' => fake()->numberBetween(0, 400),
            'hourly_rate' => fake()->numberBetween(3000, 15000),
            'base_salary' => null,
            'status' => 'active',
            'contract_type' => 'vacataire',
        ];
    }

    public function permanent(): static
    {
        return $this->state(fn() => [
            'contract_type' => 'CDI',
            'base_salary' => fake()->numberBetween(300000, 800000),
            'hourly_rate' => 0,
        ]);
    }
}
