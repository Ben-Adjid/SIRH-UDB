<?php

namespace Database\Factories;

use App\Models\Schedule;
use App\Models\Teacher;
use App\Models\Subject;
use App\Models\Classroom;
use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduleFactory extends Factory
{
    protected $model = Schedule::class;

    public function definition(): array
    {
        $days = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
        return [
            'teacher_id' => Teacher::factory(),
            'subject_id' => Subject::factory(),
            'classroom_id' => Classroom::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'day_of_week' => fake()->randomElement($days),
            'start_time' => sprintf('%02d:00', fake()->numberBetween(8, 16)),
            'end_time' => sprintf('%02d:00', fake()->numberBetween(17, 19)),
            'level' => fake()->randomElement(['L1', 'L2', 'L3', 'M1', 'M2']),
            'semester' => fake()->randomElement(['S1', 'S2']),
            'status' => 'active',
            'type' => 'course',
            'is_archived' => false,
        ];
    }

    public function exam(): static {
        return $this->state(fn() => [
            'type' => 'exam',
            'exam_type' => fake()->randomElement(['DS', 'Examen', 'Rattrapage']),
            'coefficient' => fake()->randomElement([1, 2, 3]),
        ]);
    }
}
