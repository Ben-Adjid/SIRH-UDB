<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->year('year');
            $table->integer('month');
            $table->decimal('base_salary', 12, 2);
            $table->decimal('gross_salary', 12, 2);
            $table->decimal('deductions', 12, 2)->default(0);
            $table->decimal('net_salary', 12, 2);
            $table->enum('status', ['draft', 'calculated', 'validated', 'paid'])->default('draft');
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->json('calculation_details')->nullable();
            $table->timestamps();

            $table->unique(['teacher_id', 'year', 'month']);
            $table->index(['teacher_id', 'year', 'month']);
        });

        Schema::create('payroll_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained('payrolls')->cascadeOnDelete();
            $table->string('description');
            $table->enum('type', ['earning', 'deduction']);
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('payroll_details');
        Schema::dropIfExists('payrolls');
    }
};
