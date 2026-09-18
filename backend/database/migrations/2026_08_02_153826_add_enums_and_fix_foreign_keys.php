<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remplacer cascadeOnDelete par restrictOnDelete pour éviter 
        // la suppression en cascade silencieuse des schedules/homeworks
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->foreign('subject_id')->references('id')->on('subjects')->restrictOnDelete();
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->dropForeign(['classroom_id']);
            $table->foreign('classroom_id')->references('id')->on('classrooms')->restrictOnDelete();
        });

        Schema::table('homeworks', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->foreign('subject_id')->references('id')->on('subjects')->restrictOnDelete();
        });

        Schema::table('homeworks', function (Blueprint $table) {
            $table->dropForeign(['classroom_id']);
            $table->foreign('classroom_id')->references('id')->on('classrooms')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->foreign('subject_id')->references('id')->on('subjects')->cascadeOnDelete();
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->dropForeign(['classroom_id']);
            $table->foreign('classroom_id')->references('id')->on('classrooms')->cascadeOnDelete();
        });

        Schema::table('homeworks', function (Blueprint $table) {
            $table->dropForeign(['subject_id']);
            $table->foreign('subject_id')->references('id')->on('subjects')->cascadeOnDelete();
        });

        Schema::table('homeworks', function (Blueprint $table) {
            $table->dropForeign(['classroom_id']);
            $table->foreign('classroom_id')->references('id')->on('classrooms')->cascadeOnDelete();
        });
    }
};
