<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Payroll;
use App\Models\Attendance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RestoreBackupSeeder extends Seeder
{
    public function run(): void
    {
        $backupFile = storage_path('app/backups/sirh_db_rows_removed_2026-09-27.json');

        if (!file_exists($backupFile)) {
            $this->command->error('Backup file not found: ' . $backupFile);
            return;
        }

        $backup = json_decode(file_get_contents($backupFile), true);

        if (!$backup) {
            $this->command->error('Failed to parse backup JSON');
            return;
        }

        $this->command->info('Restoring backup data...');

        // Restore payrolls
        if (isset($backup['payrolls']) && is_array($backup['payrolls'])) {
            $count = 0;
            foreach ($backup['payrolls'] as $payroll) {
                try {
                    Payroll::create([
                        'id' => $payroll['id'],
                        'teacher_id' => $payroll['teacher_id'],
                        'year' => $payroll['year'],
                        'month' => $payroll['month'],
                        'base_salary' => $payroll['base_salary'],
                        'honorarium_amount' => $payroll['honorarium_amount'],
                        'brs_amount' => $payroll['brs_amount'],
                        'payment_method' => $payroll['payment_method'],
                        'gross_salary' => $payroll['gross_salary'],
                        'deductions' => $payroll['deductions'],
                        'net_salary' => $payroll['net_salary'],
                        'status' => $payroll['status'],
                        'validated_by' => $payroll['validated_by'],
                        'validated_at' => $payroll['validated_at'],
                        'calculation_details' => $payroll['calculation_details'],
                        'created_at' => $payroll['created_at'],
                        'updated_at' => $payroll['updated_at'],
                    ]);
                    $count++;
                } catch (\Exception $e) {
                    $this->command->warn("Failed to restore payroll ID {$payroll['id']}: " . $e->getMessage());
                }
            }
            $this->command->info("✅ Restored {$count} payroll records");
        }

        // Restore attendances
        if (isset($backup['attendances']) && is_array($backup['attendances'])) {
            $count = 0;
            foreach ($backup['attendances'] as $attendance) {
                try {
                    Attendance::create([
                        'id' => $attendance['id'],
                        'user_id' => $attendance['user_id'],
                        'teacher_id' => $attendance['teacher_id'],
                        'attendance_date' => $attendance['attendance_date'],
                        'check_in_time' => $attendance['check_in_time'],
                        'check_out_time' => $attendance['check_out_time'],
                        'status' => $attendance['status'],
                        'hours_worked' => $attendance['hours_worked'],
                        'created_at' => $attendance['created_at'],
                        'updated_at' => $attendance['updated_at'],
                    ]);
                    $count++;
                } catch (\Exception $e) {
                    $this->command->warn("Failed to restore attendance ID {$attendance['id']}: " . $e->getMessage());
                }
            }
            $this->command->info("✅ Restored {$count} attendance records");
        }

        $this->command->info('✅ Backup restoration complete!');
    }
}
