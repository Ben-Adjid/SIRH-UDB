<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('homework_id')->constrained('homeworks')->cascadeOnDelete();
            $table->string('student_id');
            $table->string('student_name');
            $table->decimal('score', 5, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->enum('status', ['pending', 'graded', 'reviewed'])->default('pending');
            $table->timestamps();
            
            $table->unique(['homework_id', 'student_id']);
            $table->index(['teacher_id', 'homework_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('grades'); }
};
