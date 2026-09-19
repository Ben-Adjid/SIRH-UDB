<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use App\Models\Teacher;

return new class extends Migration
{
    public function up(): void
    {
        // D'abord, élargir la colonne pin_code pour accommoder les hashs bcrypt (~60 caractères)
        Schema::table('teachers', function (Blueprint $table) {
            $table->string('pin_code', 255)->change()->nullable();
        });

        // Ensuite, hasher tous les PINs plaintext existants
        foreach (Teacher::whereNotNull('pin_code')->get() as $teacher) {
            // Si le PIN n'est pas déjà hashé (ne commence pas par $2y$ ou $2b$)
            if (!str_starts_with($teacher->pin_code, '$2y$') && !str_starts_with($teacher->pin_code, '$2b$')) {
                // Hasher le PIN
                $teacher->update(['pin_code' => Hash::make($teacher->pin_code)]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->string('pin_code', 6)->change()->nullable();
        });
    }
};
