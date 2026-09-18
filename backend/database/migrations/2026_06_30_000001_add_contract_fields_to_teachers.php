<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('teachers', function (Blueprint $table) {
            $table->enum('contract_type', ['CDI', 'CDD', 'vacataire'])->default('CDI')->after('status');
            $table->date('contract_start_date')->nullable()->after('contract_type');
            $table->date('contract_end_date')->nullable()->after('contract_start_date');
        });
    }

    public function down(): void {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropColumn(['contract_type', 'contract_start_date', 'contract_end_date']);
        });
    }
};
