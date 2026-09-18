<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('start_year');
            $table->integer('end_year');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->unique(['start_year', 'end_year']);
        });

        // Seed the current academic year
        DB::table('academic_years')->insert([
            ['name' => '2025-2026', 'start_year' => 2025, 'end_year' => 2026, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => '2024-2025', 'start_year' => 2024, 'end_year' => 2025, 'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::table('schedules', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->after('classroom_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropForeign(['academic_year_id']);
            $table->dropColumn('academic_year_id');
        });
        Schema::dropIfExists('academic_years');
    }
};
