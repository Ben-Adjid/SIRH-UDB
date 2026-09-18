<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->decimal('honorarium_amount', 12, 2)->nullable()->after('base_salary');
            $table->decimal('brs_amount', 12, 2)->default(0)->after('honorarium_amount');
            $table->enum('payment_method', ['check', 'cash'])->nullable()->after('brs_amount');
        });
    }

    public function down(): void {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn(['honorarium_amount', 'brs_amount', 'payment_method']);
        });
    }
};
