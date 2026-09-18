<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->cascadeOnDelete();
        });

        $pairs = DB::table('attendances')
            ->join('teachers', 'attendances.teacher_id', '=', 'teachers.id')
            ->whereNull('attendances.user_id')
            ->select('attendances.id as attendance_id', 'teachers.user_id as user_id')
            ->get();

        foreach ($pairs as $pair) {
            DB::table('attendances')->where('id', $pair->attendance_id)->update(['user_id' => $pair->user_id]);
        }

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign(['teacher_id']);
            $table->dropIndex(['teacher_id', 'attendance_date']);
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('teacher_id')->nullable()->change();
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->unique(['user_id', 'attendance_date']);
        });
    }

    public function down(): void {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id', 'attendance_date']);
            $table->dropColumn('user_id');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('teacher_id')->nullable(false)->change();
            $table->foreign('teacher_id')->references('id')->on('teachers')->cascadeOnDelete();
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->unique(['teacher_id', 'attendance_date']);
        });
    }
};
