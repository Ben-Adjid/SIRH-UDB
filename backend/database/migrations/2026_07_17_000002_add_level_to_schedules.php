<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('schedules', function (Blueprint $table) {
            $table->enum('level', ['licence', 'master', 'doctorat'])->nullable()->after('classroom_id');
        });
    }
    public function down(): void {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('level');
        });
    }
};
