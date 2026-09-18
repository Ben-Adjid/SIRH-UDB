<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Ufr;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UfrAccessSeeder extends Seeder
{
    public function run(): void
    {
        $heads = [
            ['code' => 'UFR-GJ',  'email' => 'academic@sirh.local',     'name' => 'Chef UFR - Génie Juridique'],
            ['code' => 'UFR-GGE', 'email' => 'academic-gge@sirh.local', 'name' => 'Chef UFR - Génie Gestion Economique'],
            ['code' => 'UFR-ST',  'email' => 'academic-st@sirh.local',  'name' => 'Chef UFR - Sciences et Technologies'],
            ['code' => 'UFR-LA',  'email' => 'academic-la@sirh.local',  'name' => 'Chef UFR - Langues et Affaires'],
        ];

        $password = 'password';

        foreach ($heads as $head) {
            $ufr = Ufr::where('code', $head['code'])->first();
            if (!$ufr) {
                $this->command->warn("UFR {$head['code']} introuvable, ignoré.");
                continue;
            }

            $user = User::withTrashed()->where('email', $head['email'])->first();
            if (!$user) {
                $user = User::create([
                    'name'                => $head['name'],
                    'email'               => $head['email'],
                    'password'            => Hash::make($password),
                    'status'              => 'active',
                    'email_verified_at'   => now(),
                    'must_change_password' => false,
                ]);
            } else {
                $user->restore();
                $user->update([
                    'name'                => $head['name'],
                    'password'            => Hash::make($password),
                    'status'              => 'active',
                    'must_change_password' => false,
                ]);
            }

            $user->syncRoles(['academic_manager']);

            $ufr->update(['head_id' => $user->id]);

            $this->command->info("✅ {$head['email']} → {$ufr->name} ({$ufr->code})");
        }

        $this->command->info("Mot de passe : {$password}");
    }
}
