<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Ufr;
use App\Models\TeachingType;

class UfrAndTeachingTypeSeeder extends Seeder {

    public function run(): void {
        $ufrs = [
            ['name' => 'Génie Juridique', 'code' => 'UFR-GJ', 'description' => 'Formation en droit privé, droit public, droit des affaires et science politique'],
            ['name' => 'Génie Gestion Economique', 'code' => 'UFR-GGE', 'description' => 'Formation en économie, gestion, finance et comptabilité'],
            ['name' => 'Sciences et Technologies', 'code' => 'UFR-ST', 'description' => 'Formation en mathématiques, physique, informatique et chimie'],
            ['name' => 'Langues et Affaires', 'code' => 'UFR-LA', 'description' => 'Formation en langues, traduction, communication et affaires internationales'],
        ];

        foreach ($ufrs as $ufr) {
            Ufr::firstOrCreate(['code' => $ufr['code']], $ufr);
        }

        $teachingTypes = [
            ['name' => 'Cours Magistral', 'code' => 'CM', 'hourly_rate' => 5000, 'description' => 'Cours magistral en amphitheatre'],
            ['name' => 'Travaux Diriges', 'code' => 'TD', 'hourly_rate' => 5000, 'description' => 'Travaux diriges en petit groupe'],
            ['name' => 'Travaux Pratiques', 'code' => 'TP', 'hourly_rate' => 5000, 'description' => 'Travaux pratiques en laboratoire/salle'],
        ];

        foreach ($teachingTypes as $tt) {
            TeachingType::firstOrCreate(['code' => $tt['code']], $tt);
        }
    }
}
