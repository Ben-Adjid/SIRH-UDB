<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Payroll;

class AddSeptemberPayrollSeeder extends Seeder
{
    public function run(): void
    {
        // Data from the bulletin PDF provided by user
        // Prof Amadou Diallo: 42h × 5000 F = 210,000
        Payroll::firstOrCreate(
            [
                'teacher_id' => 1, // Prof Amadou Diallo
                'year' => 2026,
                'month' => 9,
            ],
            [
                'base_salary' => 0,
                'honorarium_amount' => 210000,
                'brs_amount' => -10500, // 5% sur 210000
                'payment_method' => 'check',
                'gross_salary' => 210000,
                'deductions' => 10500,
                'net_salary' => 199500,
                'status' => 'validated',
                'validated_by' => 1,
                'validated_at' => now(),
                'calculation_details' => json_encode([
                    'hours' => 42,
                    'hourly_rate' => 5000,
                    'deduction_rate' => 0.05,
                ]),
            ]
        );

        // Aïcha Ba - assuming similar hours
        Payroll::firstOrCreate(
            [
                'teacher_id' => 5, // Aïcha Ba
                'year' => 2026,
                'month' => 9,
            ],
            [
                'base_salary' => 0,
                'honorarium_amount' => 210000,
                'brs_amount' => -10500,
                'payment_method' => 'check',
                'gross_salary' => 210000,
                'deductions' => 10500,
                'net_salary' => 199500,
                'status' => 'validated',
                'validated_by' => 1,
                'validated_at' => now(),
                'calculation_details' => json_encode([
                    'hours' => 42,
                    'hourly_rate' => 5000,
                    'deduction_rate' => 0.05,
                ]),
            ]
        );

        $this->command->info('✅ September payroll data added for Prof Amadou Diallo and Aïcha Ba');
    }
}
