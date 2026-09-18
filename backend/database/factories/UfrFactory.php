<?php

namespace Database\Factories;

use App\Models\Ufr;
use Illuminate\Database\Eloquent\Factories\Factory;

class UfrFactory extends Factory
{
    protected $model = Ufr::class;

    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Sciences et Technologies', 'Droit et Sciences Politiques',
            'Lettres et Sciences Humaines', 'Économie et Gestion',
            'Médecine et Pharmacie', 'Sciences de l\'Ingénieur'
        ]);
        return [
            'name' => $name,
            'code' => 'UFR-' . strtoupper(substr($name, 0, 3)),
            'description' => 'UFR de ' . $name,
        ];
    }
}
