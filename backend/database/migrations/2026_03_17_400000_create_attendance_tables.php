<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('attendance_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->cascadeOnDelete();
            $table->string('code')->unique();
            $table->enum('status', ['active', 'inactive', 'expired'])->default('active');
            $table->dateTime('generated_at');
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();

            $table->index(['teacher_id', 'code']);
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->enum('status', ['present', 'absent', 'late', 'half_day'])->default('present');
            $table->integer('hours_worked')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['teacher_id', 'attendance_date']);
            $table->index(['teacher_id', 'attendance_date']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('attendance_codes');
    }
};
