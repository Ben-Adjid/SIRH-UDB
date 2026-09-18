<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('homeworks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('scheduled_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('duration_minutes')->default(120);
            $table->enum('status', ['planned', 'published', 'completed', 'cancelled', 'approved'])->default('planned');
            $table->integer('max_score')->default(100);
            $table->timestamps();
            
            $table->index(['teacher_id', 'scheduled_date']);
        });
    }
    public function down(): void { Schema::dropIfExists('homeworks'); }
};
