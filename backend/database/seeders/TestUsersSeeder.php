<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\Teacher;

class TestUsersSeeder extends Seeder
{
    public function run(): void
    {
        // Create a default UFR if none exists
        $defaultUfr = \App\Models\Ufr::firstOrCreate(
            ['code' => 'UFR-GJ'],
            ['name' => 'Unité de Formation et de Recherche', 'description' => 'UFR par défaut']
        );

        $users = [
            ['name' => 'Admin Système',     'email' => 'admin@sirh.local',     'role' => 'admin'],
            ['name' => 'Responsable RH',     'email' => 'rh@sirh.local',         'role' => 'hr_manager'],
            ['name' => 'Secrétaire',        'email' => 'secretaire@sirh.local', 'role' => 'hr_manager'],
            ['name' => 'Chef UFR',           'email' => 'academic@sirh.local',   'role' => 'academic_manager'],
            ['name' => 'Comptable',          'email' => 'compta@sirh.local',     'role' => 'accountant'],
            ['name' => 'Prof Amadou Diallo', 'email' => 'prof1@sirh.local',      'role' => 'teacher'],
            ['name' => 'Prof Fatou Sow',     'email' => 'prof2@sirh.local',       'role' => 'teacher'],
            ['name' => 'Prof Moussa Ndiaye', 'email' => 'prof3@sirh.local',       'role' => 'teacher'],
            ['name' => 'Enseignant Test',    'email' => 'prof@sirh.local',       'role' => 'teacher'],
        ];

        $password = 'password';
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

            $userData = [
                'name'              => $data['name'],
                'email'             => $data['email'],
                'password'          => $hashed,
                'email_verified_at' => now(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ];

            // Add optional columns if they exist in the schema
            if (Schema::hasColumn('users', 'status')) {
                $userData['status'] = 'active';
            }
            if (Schema::hasColumn('users', 'must_change_password')) {
                $userData['must_change_password'] = false;
            }
            if (Schema::hasColumn('users', 'is_activated')) {
                $userData['is_activated'] = true;
            }
            if (Schema::hasColumn('users', 'activated_at')) {
                $userData['activated_at'] = now();
            }

            $userId = DB::table('users')->insertGetId($userData);

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
                    'ufr_id'       => $defaultUfr->id,
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
