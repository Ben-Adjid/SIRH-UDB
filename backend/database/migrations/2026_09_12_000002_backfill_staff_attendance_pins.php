<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleIds = DB::table('roles')
            ->whereIn('name', ['admin', 'hr_manager', 'hr_manager', 'accountant', 'academic_manager'])
            ->pluck('id');
        $used = [];

        DB::table('users')
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->where('model_has_roles.model_type', 'App\\Models\\User')
            ->whereIn('model_has_roles.role_id', $roleIds)
            ->whereNull('users.attendance_pin')
            ->select('users.id')
            ->distinct()
            ->orderBy('users.id')
            ->get()
            ->each(function ($user) use (&$used) {
                $pin = 4000 + ($user->id % 5000);
                while (in_array((string) $pin, $used, true)) {
                    $pin++;
                    if ($pin > 9999) $pin = 4000;
                }
                $used[] = (string) $pin;
                DB::table('users')->where('id', $user->id)->update([
                    'attendance_pin' => Crypt::encryptString((string) $pin),
                ]);
            });
    }

    public function down(): void
    {
        // PIN values are user-managed after this migration and must not be erased.
    }
};