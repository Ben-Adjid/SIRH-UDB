<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Teacher;
use Illuminate\Support\Facades\Hash;

class GeneratePinCodesSeeder extends Seeder
{
    public function run(): void
    {
        echo "\n🔐 Génération des codes PIN pour les enseignants...\n";

        $teachers = Teacher::all();
        
        if ($teachers->isEmpty()) {
            echo "❌ Aucun enseignant trouvé\n";
            return;
        }

        foreach ($teachers as $teacher) {
            if (!$teacher->pin_code) {
                // Générer un PIN aléatoire 4-6 chiffres
                $pin = str_pad(rand(1000, 999999), 4, '0', STR_PAD_LEFT);
                
                // Hasher et sauvegarder
                $teacher->update([
                    'pin_code' => Hash::make($pin),
                    'pin_code_plain' => $pin // Stockage temporaire pour affichage
                ]);
                
                echo "  ✓ " . $teacher->user->name . " → PIN: $pin\n";
            } else {
                echo "  ✓ " . $teacher->user->name . " → PIN déjà configuré\n";
            }
        }

        echo "\n✅ PINs générés avec succès!\n";
    }
}
