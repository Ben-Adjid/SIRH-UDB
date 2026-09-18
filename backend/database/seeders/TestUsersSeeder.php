<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Teacher;

class TestUsersSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Admin Système',     'email' => 'admin@sirh.local',     'role' => 'admin'],
            ['name' => 'Responsable RH',     'email' => 'rh@sirh.local',         'role' => 'hr_manager'],
            ['name' => 'Secrétaire',        'email' => 'secretaire@sirh.local', 'role' => 'secretary'],
            ['name' => 'Chef UFR',           'email' => 'academic@sirh.local',   'role' => 'academic_manager'],
            ['name' => 'Comptable',          'email' => 'compta@sirh.local',     'role' => 'accountant'],
            ['name' => 'Prof Amadou Diallo', 'email' => 'prof1@sirh.local',      'role' => 'teacher'],
            ['name' => 'Prof Fatou Sow',     'email' => 'prof2@sirh.local',       'role' => 'teacher'],
            ['name' => 'Prof Moussa Ndiaye', 'email' => 'prof3@sirh.local',       'role' => 'teacher'],
            ['name' => 'Enseignant Test',    'email' => 'prof@sirh.local',       'role' => 'teacher'],
        ];

        $password = 'Changez-Moi-2026!';
        $hashed = Hash::make($password);

        foreach ($users as $data) {
            // Supprime TOUS les enregistrements (soft-deleted inclus) et leurs rôles
            $existing = User::withTrashed()->where('email', $data['email'])->first();
            if ($existing) {
                DB::table('model_has_roles')
                    ->where('model_type', 'App\Models\User')
                    ->where('model_id', $existing->id)
                    ->delete();
                $existing->forceDelete();
            }

            $userId = DB::table('users')->insertGetId([
                'name'                => $data['name'],
                'email'               => $data['email'],
                'password'            => $hashed,
                'status'              => 'active',
                'email_verified_at'   => now(),
                'must_change_password' => false,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            $roleId = DB::table('roles')->where('name', $data['role'])->value('id');
            if ($roleId) {
                DB::table('model_has_roles')->insert([
                    'role_id'    => $roleId,
                    'model_type' => 'App\Models\User',
                    'model_id'   => $userId,
                ]);
            }

            if ($data['role'] === 'teacher') {
                do {
                    $pin = str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
                } while (Teacher::where('pin_code', $pin)->exists());

                Teacher::create([
                    'user_id'      => $userId,
                    'ufr_id'       => \App\Models\Ufr::first()?->id ?? 1,
                    'employee_id'  => 'ENS-' . str_pad($userId, 5, '0', STR_PAD_LEFT),
                    'pin_code'     => $pin,
                    'hire_date'    => now(),
                    'volume_hours' => 200,
                    'hourly_rate'  => 5000,
                    'status'       => 'active',
                ]);
            }

            $this->command->info("✅ {$data['email']}");
        }

        $academic = User::where('email', 'academic@sirh.local')->first();
        if ($academic) {
            \App\Models\Ufr::where('code', 'UFR-GJ')->update(['head_id' => $academic->id]);
        }

        $this->command->info('');
        $this->command->info("✅ Mot de passe unique : {$password}");
    }
}
