<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        // 1. Rename teachers.department_id → teachers.ufr_id
        Schema::table('teachers', function (Blueprint $table) {
            $table->renameColumn('department_id', 'ufr_id');
        });

        // 2. Drop departments.ufr_id FK and column
        Schema::table('departments', function (Blueprint $table) {
            try {
                $table->dropForeign(['ufr_id']);
            } catch (\Throwable) {}
            $table->dropColumn('ufr_id');
        });

        // 3. Drop old ufrs table
        Schema::dropIfExists('ufrs');

        // 4. Rename departments → ufrs
        Schema::rename('departments', 'ufrs');

        // 5. Add FK on teachers.ufr_id → ufrs.id
        Schema::table('teachers', function (Blueprint $table) {
            $table->foreign('ufr_id')->references('id')->on('ufrs')->restrictOnDelete();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        // Reverse: drop FK on teachers.ufr_id
        Schema::table('teachers', function (Blueprint $table) {
            try {
                $table->dropForeign(['ufr_id']);
            } catch (\Throwable) {}
        });

        // Rename ufrs → departments
        Schema::rename('ufrs', 'departments');

        // Recreate old ufrs table
        Schema::create('ufrs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->foreignId('head_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Re-add ufr_id to departments
        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('ufr_id')->nullable()->constrained('ufrs')->nullOnDelete();
        });

        // Rename teachers.ufr_id → department_id
        Schema::table('teachers', function (Blueprint $table) {
            $table->renameColumn('ufr_id', 'department_id');
            $table->foreign('department_id')->references('id')->on('departments')->restrictOnDelete();
        });

        Schema::enableForeignKeyConstraints();
    }
};
