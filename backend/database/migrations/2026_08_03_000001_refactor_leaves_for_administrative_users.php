<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('teacher_id')->constrained('users')->nullOnDelete();
        });

        DB::statement(
            'UPDATE leaves SET user_id = (SELECT user_id FROM teachers WHERE teachers.id = leaves.teacher_id) WHERE teacher_id IS NOT NULL'
        );

        Schema::table('leaves', function (Blueprint $table) {
            $table->dropForeign(['teacher_id']);
            $table->foreignId('teacher_id')->nullable()->change();
            $table->foreign('teacher_id')->references('id')->on('teachers')->nullOnDelete();
        });

        Schema::table('leave_balances', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('teacher_id')->constrained('users')->nullOnDelete();
        });

        DB::statement(
            'UPDATE leave_balances SET user_id = (SELECT user_id FROM teachers WHERE teachers.id = leave_balances.teacher_id) WHERE teacher_id IS NOT NULL'
        );

        Schema::table('leave_balances', function (Blueprint $table) {
            $table->dropForeign(['teacher_id']);
            $table->foreignId('teacher_id')->nullable()->change();
            $table->foreign('teacher_id')->references('id')->on('teachers')->nullOnDelete();
            $table->unique(['user_id', 'leave_type_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::table('leave_balances', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'leave_type_id', 'year']);
            $table->dropForeign(['teacher_id']);
            $table->foreignId('teacher_id')->nullable(false)->change();
            $table->foreign('teacher_id')->references('id')->on('teachers')->cascadeOnDelete();
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('leaves', function (Blueprint $table) {
            $table->dropForeign(['teacher_id']);
            $table->foreignId('teacher_id')->nullable(false)->change();
            $table->foreign('teacher_id')->references('id')->on('teachers')->cascadeOnDelete();
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
