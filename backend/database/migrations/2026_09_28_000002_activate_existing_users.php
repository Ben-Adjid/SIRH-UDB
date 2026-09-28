<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ✅ Activer tous les comptes existants qui se sont déjà connectés
        DB::table('users')
            ->where('last_login_at', '!=', null)
            ->update([
                'is_activated' => true,
                'activated_at' => DB::raw('last_login_at'),
            ]);

        // ✅ Activer les comptes actifs créés il y a plus d'une semaine
        DB::table('users')
            ->where('status', 'active')
            ->where('created_at', '<', now()->subDays(7))
            ->where('is_activated', false)
            ->update([
                'is_activated' => true,
                'activated_at' => DB::raw('created_at'),
            ]);
    }

    public function down(): void
    {
        DB::table('users')->update([
            'is_activated' => false,
            'activated_at' => null,
        ]);
    }
};
