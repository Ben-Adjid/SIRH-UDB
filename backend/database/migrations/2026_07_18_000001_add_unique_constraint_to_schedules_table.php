<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('schedules', function (Blueprint $table) {
            $table->index(['classroom_id', 'day_of_week', 'start_time', 'academic_year_id'], 'schedules_classroom_day_index');
            $table->index(['level', 'semester']);
        });
    }

    public function down(): void {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex('schedules_classroom_day_index');
            $table->dropIndex(['level', 'semester']);
        });
    }
};
