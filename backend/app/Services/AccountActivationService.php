<?php

namespace App\Services;

use App\Mail\AccountActivationMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AccountActivationService
{
    public function sendActivationLink(User $user): void
    {
        // Créer un token d'activation via le broker de réinitialisation de mot de passe
        $token = Password::broker()->createToken($user);

        // Envoyer l'email
        Mail::send(new AccountActivationMail(
            $user,
            $token,
            config('app.frontend_url', 'http://localhost:3000')
        ));
    }

    public function activateAccount(string $email, string $token, string $password): bool
    {
        // Vérifier le token
        if (!Password::broker()->tokenExists($user = User::where('email', $email)->first(), $token)) {
            return false;
        }

        // Mettre à jour le mot de passe et marquer comme activé
        $user->update([
            'password' => Hash::make($password),
            'is_activated' => true,
            'activated_at' => now(),
        ]);

        // Consommer le token
        Password::broker()->deleteToken($user);

        // Révoquer toutes les sessions/tokens existants
        $user->tokens()->delete();

        return true;
    }
}
