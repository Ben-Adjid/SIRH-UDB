<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('level');
        });
        Schema::table('schedules', function (Blueprint $table) {
            $table->enum('level', ['L1', 'L2', 'L3', 'M1', 'M2'])->nullable()->after('classroom_id');
            $table->enum('semester', ['S1', 'S2'])->nullable()->after('level');
        });
    }
    public function down(): void {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn(['level', 'semester']);
        });
    }
};
