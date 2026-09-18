<?php

namespace Database\Factories;

use App\Models\Classroom;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClassroomFactory extends Factory
{
    protected $model = Classroom::class;

    public function definition(): array
    {
        return [
            'name' => 'Salle ' . fake()->unique()->bothify('?###'),
            'code' => fake()->unique()->bothify('??###'),
            'capacity' => fake()->numberBetween(15, 120),
            'type' => fake()->randomElement(['classroom', 'lab', 'amphitheater']),
        ];
    }
}
