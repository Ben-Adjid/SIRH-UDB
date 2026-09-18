<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('schedule_id')->nullable()->after('teacher_id')->constrained('schedules')->cascadeOnDelete();
        });

        // Nouvel index unique ajouté AVANT de supprimer l'ancien, pour que la
        // clé étrangère sur user_id conserve un index de référence.
        Schema::table('attendances', function (Blueprint $table) {
            $table->unique(['user_id', 'attendance_date', 'schedule_id']);
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'attendance_date']);
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->unique(['user_id', 'attendance_date']);
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'attendance_date', 'schedule_id']);
            $table->dropConstrainedForeignId('schedule_id');
        });
    }
};
