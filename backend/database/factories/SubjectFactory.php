<?php

namespace Database\Factories;

use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Mathématiques', 'Physique', 'Chimie', 'Informatique', 'Français', 'Anglais', 'Histoire', 'Géographie', 'Biologie', 'Philosophie', 'Économie', 'Droit']),
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'level' => fake()->randomElement(['L1', 'L2', 'L3', 'M1', 'M2']),
            'credit_hours' => fake()->randomElement([1, 2, 3, 4]),
        ];
    }
}
