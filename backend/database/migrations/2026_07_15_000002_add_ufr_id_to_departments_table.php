<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('ufr_id')->nullable()->after('id')->constrained('ufrs')->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['ufr_id']);
            $table->dropColumn('ufr_id');
        });
    }
};
