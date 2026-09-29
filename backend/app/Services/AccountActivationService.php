<?php

namespace App\Services;

use App\Mail\AccountActivationMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Carbon;

class AccountActivationService
{
    public function sendActivationLink(User $user): void
    {
        $token = Password::broker()->createToken($user);

        Mail::to($user->email)->send(new AccountActivationMail(
            $user,
            $token,
            config('app.frontend_url', 'http://localhost:3000')
        ));
    }

    public function activateAccount(string $email, string $token, string $password): bool
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            return false;
        }

        // ✅ Vérifier le token dans la base de données
        $resetToken = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (!$resetToken) {
            return false;
        }

        // ✅ Vérifier que le token correspond
        if (!Hash::check($token, $resetToken->token)) {
            return false;
        }

        // ✅ Vérifier que le token n'a pas expiré (48 heures = 2880 minutes)
        if (Carbon::parse($resetToken->created_at)->diffInMinutes(now()) > 2880) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            return false;
        }

        // ✅ Mettre à jour le mot de passe et marquer comme activé
        $user->update([
            'password' => Hash::make($password),
            'is_activated' => true,
            'activated_at' => now(),
        ]);

        // ✅ Consommer le token (le supprimer)
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        // ✅ Révoquer toutes les sessions/tokens existants
        $user->tokens()->delete();

        return true;
    }
}
