<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('schedules', function (Blueprint $table) {
            $table->foreignId('teaching_type_id')->nullable()->after('subject_id')->constrained('teaching_types')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropForeign(['teaching_type_id']);
            $table->dropColumn('teaching_type_id');
        });
    }
};
