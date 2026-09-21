<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('teacher_pin_displays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->onDelete('cascade');
            $table->string('pin_plain'); // PIN en clair (temporaire)
            $table->string('pin_hashed'); // PIN hashe pour validation
            $table->timestamp('display_until')->nullable(); // Afficher jusqu'à cette date
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('teacher_pin_displays');
    }
};
