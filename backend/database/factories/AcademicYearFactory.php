<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

class AcademicYearFactory extends Factory
{
    protected $model = AcademicYear::class;

    public function definition(): array
    {
        $year = fake()->numberBetween(2020, 2030);
        return [
            'name' => $year . '-' . ($year + 1),
            'start_year' => $year,
            'end_year' => $year + 1,
            'is_active' => false,
        ];
    }
}
