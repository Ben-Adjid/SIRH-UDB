<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Holiday;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('name');
            $table->string('country')->default('SN');
            $table->timestamps();
            $table->unique(['date', 'country']);
        });

        // Insert Senegalese holidays for 2026
        Holiday::create(['date' => '2026-01-01', 'name' => 'Jour de l\'an']);
        Holiday::create(['date' => '2026-02-18', 'name' => 'Tabaski']);
        Holiday::create(['date' => '2026-03-30', 'name' => 'Korité']);
        Holiday::create(['date' => '2026-04-11', 'name' => 'Aïd-el-Fitr']);
        Holiday::create(['date' => '2026-04-14', 'name' => 'Fête nationale']);
        Holiday::create(['date' => '2026-05-01', 'name' => 'Fête du Travail']);
        Holiday::create(['date' => '2026-05-26', 'name' => 'Aïd-el-Adha']);
        Holiday::create(['date' => '2026-06-15', 'name' => 'Assomption (Ascension du Prophète)']);
        Holiday::create(['date' => '2026-08-15', 'name' => 'Assomption de Marie']);
        Holiday::create(['date' => '2026-11-01', 'name' => 'Toussaint']);
        Holiday::create(['date' => '2026-11-14', 'name' => 'Indépendance']);
        Holiday::create(['date' => '2026-12-25', 'name' => 'Noël']);
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
