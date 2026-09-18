<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('attendances', function (Blueprint $table) {
            $table->boolean('is_justified')->default(false);
            $table->string('justification_status')->nullable()->after('is_justified');
            $table->foreignId('justified_by')->nullable()->after('justification_status')->constrained('users')->nullOnDelete();
            $table->timestamp('justified_at')->nullable()->after('justified_by');
        });

        Schema::table('attendance_codes', function (Blueprint $table) {
            $table->integer('validity_hours')->default(2)->after('expires_at');
        });
    }

    public function down(): void {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['is_justified', 'justification_status', 'justified_by', 'justified_at']);
        });

        Schema::table('attendance_codes', function (Blueprint $table) {
            $table->dropColumn('validity_hours');
        });
    }
};
