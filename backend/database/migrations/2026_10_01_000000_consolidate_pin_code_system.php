<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\Teacher;

return new class extends Migration
{
    /**
     * CONSOLIDATION MIGRATION - Consolidates 4 previous PIN code migrations
     * This migration should only run if the 4 previous migrations have NOT been applied.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('teachers', 'pin_code')) {
            Schema::table('teachers', function (Blueprint $table) {
                $table->string('pin_code', 255)->nullable()->unique()->after('employee_id');
            });
        }

        if (!Schema::hasColumn('users', 'attendance_pin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('attendance_pin', 255)->nullable()->after('password');
            });
        }

        // Backfill staff attendance PINs if empty
        $staffRoles = ['admin', 'hr_manager', 'hr_manager', 'accountant', 'academic_manager'];
        $used = [];
        DB::table('users')
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->where('model_has_roles.model_type', 'App\Models\User')
            ->whereIn('model_has_roles.role_id', DB::table('roles')->whereIn('name', $staffRoles)->pluck('id'))
            ->whereNull('users.attendance_pin')
            ->select('users.id')
            ->distinct()
            ->orderBy('users.id')
            ->get()
            ->each(function ($user) use (&$used) {
                $pin = 100000 + ($user->id % 900000);
                while (in_array((string) $pin, $used, true)) {
                    $pin++;
                    if ($pin > 999999) $pin = 100000;
                }
                $used[] = (string) $pin;
                DB::table('users')->where('id', $user->id)->update([
                    'attendance_pin' => Hash::make((string) $pin),
                ]);
            });

        // Hash existing plaintext PIN codes
        foreach (Teacher::whereNotNull('pin_code')->get() as $teacher) {
            if (!str_starts_with($teacher->pin_code, '$2y$') && !str_starts_with($teacher->pin_code, '$2b$')) {
                $teacher->update(['pin_code' => Hash::make($teacher->pin_code)]);
            }
        }
    }

    public function down(): void
    {
        // Consolidation migration - cannot reliably reverse
    }
};
