<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TestLogin extends Command
{
    protected $signature = 'test:login {email?}';
    protected $description = 'Teste le login de tous les comptes ou d\'un compte spécifique';

    public function handle(): void
    {
        $password = 'Changez-Moi-2026!';
        $accounts = [
            ['email' => 'admin@sirh.local',       'label' => 'Admin'],
            ['email' => 'rh@sirh.local',           'label' => 'Responsable RH'],
            ['email' => 'secretaire@sirh.local',   'label' => 'Secrétaire'],
            ['email' => 'academic@sirh.local',     'label' => 'Chef UFR'],
            ['email' => 'compta@sirh.local',       'label' => 'Comptable'],
            ['email' => 'prof1@sirh.local',        'label' => 'Prof Amadou'],
            ['email' => 'prof2@sirh.local',        'label' => 'Prof Fatou'],
            ['email' => 'prof3@sirh.local',        'label' => 'Prof Moussa'],
        ];

        $targetEmail = $this->argument('email');
        if ($targetEmail) {
            $accounts = array_filter($accounts, fn($a) => $a['email'] === $targetEmail);
        }

        $this->info('===== TEST LOGIN COMPLET =====');
        $this->info('Connexion DB : ' . config('database.default') . ' / ' . config('database.connections.' . config('database.default') . '.database'));
        $this->info('Mot de passe testé : ' . $password);
        $this->info('');

        foreach ($accounts as $account) {
            $user = DB::table('users')->where('email', $account['email'])->first();

            if (!$user) {
                $this->error("❌ {$account['label']} ({$account['email']}) : INTROUVABLE en base");
                continue;
            }

            $passOk = Hash::check($password, $user->password);
            $statusOk = $user->status === 'active';
            $has2fa = !empty($user->two_factor_enabled_at);
            $mustChangePw = !empty($user->must_change_password);

            $this->info("── {$account['label']} ({$account['email']}) ──");
            $this->info("   ID        : {$user->id}");
            $this->info("   Status    : {$user->status}" . ($statusOk ? ' ✅' : ' ❌'));
            $this->info("   Password  : " . ($passOk ? '✅ MATCH' : '❌ PAS DE MATCH'));
            $this->info("   Hash DB   : " . substr($user->password, 0, 60));
            $this->info("   2FA       : " . ($has2fa ? 'OUI (bloque le login direct)' : 'Non'));
            $this->info("   Force PW  : " . ($mustChangePw ? 'OUI (redirige vers changement)' : 'Non'));
            $this->info("   Rôles     : " . json_encode(DB::table('model_has_roles')->join('roles', 'model_has_roles.role_id', '=', 'roles.id')->where('model_id', $user->id)->pluck('name')->toArray()));

            if ($passOk && $statusOk) {
                $this->info("   ➜ LOGIN POSSIBLE ✅");
            } else {
                $this->error("   ➜ LOGIN IMPOSSIBLE ❌");
            }
            $this->info('');
        }
    }
}
