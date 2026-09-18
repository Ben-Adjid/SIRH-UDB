<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('schedules', function (Blueprint $table) {
            $table->string('type')->default('course')->after('status');
            $table->string('exam_type')->nullable()->after('type');
            $table->decimal('coefficient', 4, 2)->default(1)->after('exam_type');
        });
    }

    public function down(): void {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn(['type', 'exam_type', 'coefficient']);
        });
    }
};
