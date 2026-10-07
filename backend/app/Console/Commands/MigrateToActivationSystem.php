<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AccountActivationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigrateToActivationSystem extends Command
{
    protected $signature = 'migration:activation-system';
    protected $description = 'Migrate existing users to the new activation system and send activation links';

    public function handle()
    {
        $this->info('🔄 Migration vers le système d\'activation de compte...\n');

        // Étape 1: Marquer les comptes non-activés comme non-activés
        $nonActivatedCount = User::where('is_activated', false)->count();
        $this->info("📊 Comptes non-activés trouvés: $nonActivatedCount");

        if ($nonActivatedCount === 0) {
            $this->info('✅ Aucun compte à migrer - Tous les comptes sont déjà activés.');
            return;
        }

        // Étape 2: Envoyer des liens d'activation aux comptes non-activés
        $this->info("\n📧 Envoi des liens d'activation...");

        $usersToActivate = User::where('is_activated', false)->get();
        $sent = 0;
        $failed = 0;

        foreach ($usersToActivate as $user) {
            try {
                $activationService = app(AccountActivationService::class);
                $activationService->sendActivationLink($user);

                $this->line("  ✅ {$user->name} ({$user->email})");
                $sent++;
            } catch (\Exception $e) {
                $this->line("  ❌ {$user->name} ({$user->email}): {$e->getMessage()}");
                $failed++;
                Log::error('Migration: Erreur envoi lien activation', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Étape 3: Purger les anciens tokens de réinitialisation
        $this->info("\n🗑️  Nettoyage des anciens tokens...");

        $deletedTokens = DB::table('password_reset_tokens')
            ->where('created_at', '<', now()->subDays(30))
            ->delete();

        $this->line("  ✅ $deletedTokens anciens tokens supprimés");

        // Résumé
        $this->info("\n" . str_repeat('=', 50));
        $this->info('📊 RÉSUMÉ DE LA MIGRATION');
        $this->info(str_repeat('=', 50));
        $this->info("✅ Liens d'activation envoyés: $sent");
        $this->info("❌ Erreurs: $failed");
        $this->info("🗑️  Anciens tokens supprimés: $deletedTokens");
        $this->info(str_repeat('=', 50));

        if ($failed === 0) {
            $this->info("\n🎉 Migration complète avec succès!");
        } else {
            $this->warn("\n⚠️  $failed compte(s) n'ont pas reçu de lien d'activation. Vérifiez les logs.");
        }
    }
}
