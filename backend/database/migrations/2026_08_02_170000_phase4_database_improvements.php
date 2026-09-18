<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $softDeleteTables = [
            'academic_years', 'attendance_codes', 'leave_balances',
            'leave_types', 'notifications', 'payroll_details', 'schedules'
        ];

        foreach ($softDeleteTables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        Schema::table('leave_balances', function (Blueprint $table) {
            $table->index(['teacher_id', 'leave_type_id', 'year']);
        });

        Schema::table('payroll_details', function (Blueprint $table) {
            $table->index('payroll_id');
        });

        Schema::table('academic_years', function (Blueprint $table) {
            $table->index('is_active');
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->index('status');
            $table->index('teacher_id');
        });

        Schema::table('leaves', function (Blueprint $table) {
            $table->dropForeign(['leave_type_id']);
            $table->foreign('leave_type_id')->references('id')->on('leave_types')->restrictOnDelete();
        });

        Schema::table('leave_balances', function (Blueprint $table) {
            $table->dropForeign(['leave_type_id']);
            $table->foreign('leave_type_id')->references('id')->on('leave_types')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            $table->dropForeign(['leave_type_id']);
            $table->foreign('leave_type_id')->references('id')->on('leave_types')->cascadeOnDelete();
        });

        Schema::table('leave_balances', function (Blueprint $table) {
            $table->dropForeign(['leave_type_id']);
            $table->foreign('leave_type_id')->references('id')->on('leave_types')->cascadeOnDelete();
        });

        Schema::table('leave_balances', function (Blueprint $table) {
            $table->dropIndex(['teacher_id', 'leave_type_id', 'year']);
        });

        Schema::table('payroll_details', function (Blueprint $table) {
            $table->dropForeign(['payroll_id']);
            $table->dropIndex(['payroll_id']);
            $table->foreign('payroll_id')->references('id')->on('payrolls')->cascadeOnDelete();
        });

        Schema::table('academic_years', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['teacher_id']);
        });

        $softDeleteTables = [
            'academic_years', 'attendance_codes', 'leave_balances',
            'leave_types', 'notifications', 'payroll_details', 'schedules'
        ];

        foreach ($softDeleteTables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
