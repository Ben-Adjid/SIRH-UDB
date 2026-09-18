<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->unique();
            $table->integer('days_per_year')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        DB::table('leave_types')->insert([
            ['name' => 'Congés payés', 'code' => 'PAID', 'days_per_year' => 25, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Congé de maladie', 'code' => 'SICK', 'days_per_year' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Congé familial', 'code' => 'FAMILY', 'days_per_year' => 3, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::create('leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('duration_days');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['teacher_id', 'status']);
        });

        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->integer('year');
            $table->integer('total_days');
            $table->integer('used_days')->default(0);
            $table->integer('pending_days')->default(0);
            $table->timestamps();

            $table->unique(['teacher_id', 'leave_type_id', 'year']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leaves');
        Schema::dropIfExists('leave_types');
    }
};
