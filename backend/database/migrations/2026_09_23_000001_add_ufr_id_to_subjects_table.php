<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::hasColumn('subjects', 'ufr_id')) {
            return;
        }
        Schema::table('subjects', function (Blueprint $table) {
            $table->foreignId('ufr_id')->nullable()->after('id')->constrained('ufrs')->nullOnDelete();
        });
    }

    public function down(): void {
        if (!Schema::hasColumn('subjects', 'ufr_id')) {
            return;
        }
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ufr_id');
        });
    }
};
