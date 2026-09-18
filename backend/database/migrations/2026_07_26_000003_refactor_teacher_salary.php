<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('teachers', function (Blueprint $table) {
            $table->decimal('hourly_rate', 12, 2)->nullable()->after('volume_hours');
            $table->decimal('base_salary', 12, 2)->nullable()->change();
        });
    }

    public function down(): void {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn('hourly_rate');
            $table->decimal('base_salary', 12, 2)->nullable(false)->change();
        });
    }
};
