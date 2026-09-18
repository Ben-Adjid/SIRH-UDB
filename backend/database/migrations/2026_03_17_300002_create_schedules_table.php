<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('day_of_week');  // Monday, Tuesday, etc.
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('status', ['scheduled', 'cancelled', 'completed'])->default('scheduled');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['teacher_id', 'start_date']);
            $table->index(['classroom_id', 'start_date']);
        });
    }
    public function down(): void { Schema::dropIfExists('schedules'); }
};
