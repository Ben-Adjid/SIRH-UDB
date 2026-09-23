<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;
use App\Services\BrevoMailService;

class AuthController extends Controller {

    public static function generateTempPassword(): string
    {
        return 'UDB' . random_int(1000, 9999);
    }

    public function register(Request $request): JsonResponse {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20',
            'role' => 'required|string|exists:roles,name',
        ]);

        if (!auth()->user()->hasRole('admin') && $validated['role'] === 'admin') {
            return response()->json(['message' => 'Vous ne pouvez pas attribuer le rôle administrateur'], 403);
        }

        try {
            $result = app(\App\Services\UserCreationService::class)->createUser($validated, $validated['role']);
            $user = $result['user'];

            return response()->json([
                'message' => 'Compte créé. Un SMS avec le mot de passe temporaire a été envoyé au ' . ($validated['phone'] ?? 'numéro non renseigné'),
                'user' => $user,
            ], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }
    }

    public function login(Request $request): JsonResponse {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Rate limiting: max 5 tentatives par email sur 5 minutes
        $key = 'login_attempts_' . md5($validated['email']);
        $attempts = cache()->get($key, 0);
        if ($attempts >= 5) {
            return response()->json(['message' => 'Trop de tentatives. Réessayez dans 5 minutes.'], 429);
        }

        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            Hash::check($validated['password'], '$2y$12$1h84am4sQrZ.iMErqlH2Zu3KlkWY/Ew5tRe.PJtat2BGMmtc4FocO');
        }

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            cache()->put($key, $attempts + 1, now()->addMinutes(5));

            if ($user) {
                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'login_failed',
                    'model' => 'User',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }
            
            return response()->json(['message' => 'Identifiants invalides'], 401);
        }

        if ($user->status !== 'active') {
            return response()->json(['message' => 'Compte désactivé'], 403);
        }

        $requires2fa = !is_null($user->two_factor_enabled_at);

        if ($requires2fa) {
            $tempToken = Str::random(64);
            $tempTokenKey = '2fa_temp_token_' . md5($user->email);
            cache()->put($tempTokenKey, Hash::make($tempToken), now()->addMinutes(5));

            cache()->forget('login_attempts_' . md5($validated['email']));

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'login_2fa_pending',
                'model' => 'User',
                'model_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'message' => 'Code 2FA requis',
                'requires_2fa' => true,
                'email_verified' => !is_null($user->email_verified_at),
                'token' => $tempToken,
            ], 200);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $user->update(['last_login_at' => now()]);

        cache()->forget('login_attempts_' . md5($validated['email']));

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'login',
            'model' => 'User',
            'model_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $user->load('roles', 'teacher');

        return response()->json([
            'message' => 'Connexion réussie',
            'user' => $user,
            'token' => $token,
            'email_verified' => !is_null($user->email_verified_at),
            'requires_2fa' => false,
            'must_change_password' => (bool) $user->must_change_password,
        ], 200);
    }

    public function me(Request $request): JsonResponse {
        $user = $request->user();
        $user->load('roles', 'teacher');
        return response()->json(['user' => $user]);
    }

    public function logout(Request $request): JsonResponse {
        $user = $request->user();
        if ($user) {
            try {
                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'logout',
                    'model' => 'User',
                    'model_id' => $user->id,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            } catch (\Throwable $exception) {
                \Log::warning('Logout audit failed', ['user_id' => $user->id, 'error' => $exception->getMessage()]);
            }

            $user->tokens()->delete();
        }

        return response()->json(['message' => 'Déconnecté'], 200);
    }

    public function updateProfile(Request $request): JsonResponse {
        $user = auth()->user();

        $oldValues = [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
        ];

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'attendance_pin' => 'nullable|digits_between:4,6',
        ]);

        $updates = $validated;
        if (array_key_exists('attendance_pin', $validated)) {
            $pinValue = $validated['attendance_pin'] ?: null;
            $updates['attendance_pin'] = $pinValue;
            if ($user->hasRole('teacher')) {
                $teacher = \App\Models\Teacher::where('user_id', $user->id)->first();
                if ($teacher) {
                    $teacher->update(['pin_code' => $pinValue]);
                }
            }
        }
        if (array_key_exists('email', $validated) && $validated['email'] !== $user->email) {
            $updates['email_verified_at'] = null;
        }
        if (array_key_exists('phone', $validated) && $validated['phone'] !== $user->phone) {
            $updates['phone_verified_at'] = null;
        }
        $user->update($updates);

        $newValues = [
            'name' => $user->fresh()->name,
            'email' => $user->fresh()->email,
            'phone' => $user->fresh()->phone,
        ];

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'update_profile',
            'model' => 'User',
            'model_id' => $user->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);

        $user->load('roles', 'teacher');
        return response()->json(['message' => 'Profil mis à jour', 'user' => $user], 200);
    }

    public function changePassword(Request $request): JsonResponse {
        $validated = $request->validate([
            'current_password' => 'required',
            'new_password' => [
                'required',
                'string',
                'min:12',
                'confirmed',
                'different:current_password',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]{12,}$/',
            ],
        ]);

        $user = auth()->user();

        if ($user->must_change_password) {
            return response()->json(['message' => 'Vous devez d\'abord changer votre mot de passe via l\'écran dédié.'], 403);
        }

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json(['message' => 'Mot de passe actuel incorrect'], 422);
        }

        $user->update([
            'password' => Hash::make($validated['new_password']),
            'must_change_password' => false,
        ]);

        $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'change_password',
            'model' => 'User',
            'model_id' => $user->id,
        ]);

        return response()->json(['message' => 'Mot de passe changé'], 200);
    }

    public function forceChangePassword(Request $request): JsonResponse {
        $validated = $request->validate([
            'new_password' => [
                'required',
                'string',
                'min:12',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]{12,}$/',
            ],
        ]);

        $user = auth()->user();

        if (!$user->must_change_password) {
            return response()->json(['message' => 'Vous n\'êtes pas obligé de changer votre mot de passe.'], 422);
        }

        $user->update([
            'password' => Hash::make($validated['new_password']),
            'must_change_password' => false,
        ]);

        // Invalidate all existing tokens
        $user->tokens()->delete();

        // Invalidate 2FA temp token if any
        cache()->forget('2fa_temp_token_' . md5($user->email));

        $newToken = $user->createToken('auth_token')->plainTextToken;

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'force_change_password',
            'model' => 'User',
            'model_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $freshUser = $user->fresh()->load('roles', 'teacher');

        return response()->json([
            'message' => 'Mot de passe changé avec succès.',
            'user' => $freshUser,
            'token' => $newToken,
        ], 200);
    }

    public function forgotPassword(Request $request): JsonResponse {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            // Retourner un succès même si l'utilisateur n'existe pas (sécurité)
            return response()->json(['message' => 'Si cette adresse email est associée à un compte, vous recevrez un lien de réinitialisation.'], 200);
        }

        // Rate limiting: max 3 demandes par email sur 15 minutes
        $key = 'forgot_password_' . md5($validated['email']);
        $attempts = cache()->get($key, 0);
        if ($attempts >= 3) {
            return response()->json(['message' => 'Trop de demandes. Réessayez dans 15 minutes.'], 429);
        }

        $token = Str::random(64);
        
        \DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $validated['email']],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        cache()->put($key, $attempts + 1, now()->addMinutes(15));

        try {
            app(BrevoMailService::class)->sendResetPasswordLink($user, $token, $validated['email']);
        } catch (\Exception $e) {
            \Log::error('Erreur envoi email reset password (Brevo API): ' . $e->getMessage());
        }

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'forgot_password',
            'model' => 'User',
            'model_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(['message' => 'Si cette adresse email est associée à un compte, vous recevrez un lien de réinitialisation.'], 200);
    }

    public function resetPassword(Request $request): JsonResponse {
        $validated = $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => [
                'required',
                'string',
                'min:12',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]{12,}$/',
            ],
        ]);

        $resetToken = \DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->first();

        if (!$resetToken || !Hash::check($validated['token'], $resetToken->token)) {
            return response()->json(['message' => 'Lien de réinitialisation invalide ou expiré.'], 422);
        }

        if (now()->diffInMinutes($resetToken->created_at) > 60) {
            \DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();
            return response()->json(['message' => 'Lien de réinitialisation expiré. Demandez un nouveau lien.'], 422);
        }

        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            return response()->json(['message' => 'Utilisateur non trouvé.'], 404);
        }

        $user->update(['password' => Hash::make($validated['password'])]);
        $user->tokens()->delete();
        \DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

        event(new PasswordReset($user));

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'reset_password',
            'model' => 'User',
            'model_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(['message' => 'Mot de passe réinitialisé avec succès. Connectez-vous avec votre nouveau mot de passe.'], 200);
    }

    public function sendVerificationEmail(Request $request): JsonResponse {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Votre adresse email est déjà vérifiée.'], 200);
        }

        // Rate limiting: max 3 demandes par user sur 15 minutes
        $key = 'verify_email_' . $user->id;
        $attempts = cache()->get($key, 0);
        if ($attempts >= 3) {
            return response()->json(['message' => 'Trop de demandes. Réessayez dans 15 minutes.'], 429);
        }

        $token = Str::random(64);
        
        \DB::table('email_verification_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        cache()->put($key, $attempts + 1, now()->addMinutes(15));

        try {
            app(BrevoMailService::class)->sendVerificationEmail($user, $token, $user->email);
        } catch (\Exception $e) {
            \Log::error('Erreur envoi email verification (Brevo API): ' . $e->getMessage());
        }

        return response()->json(['message' => 'Un email de vérification a été envoyé.'], 200);
    }

    public function verifyEmail(Request $request): JsonResponse {
        $validated = $request->validate([
            'token' => 'required',
            'email' => 'required|email',
        ]);

        $verificationToken = \DB::table('email_verification_tokens')
            ->where('email', $validated['email'])
            ->first();

        if (!$verificationToken || !Hash::check($validated['token'], $verificationToken->token)) {
            return response()->json(['message' => 'Lien de vérification invalide.'], 422);
        }

        if (now()->diffInHours($verificationToken->created_at) > 24) {
            \DB::table('email_verification_tokens')->where('email', $validated['email'])->delete();
            return response()->json(['message' => 'Lien de vérification expiré. Demandez un nouveau lien.'], 422);
        }

        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            return response()->json(['message' => 'Utilisateur non trouvé.'], 404);
        }

        $user->update(['email_verified_at' => now()]);
        \DB::table('email_verification_tokens')->where('email', $validated['email'])->delete();

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'email_verified',
            'model' => 'User',
            'model_id' => $user->id,
        ]);

        return response()->json(['message' => 'Email vérifié avec succès.'], 200);
    }

    public function refreshToken(Request $request): JsonResponse {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        $currentToken = $user->currentAccessToken();
        if ($currentToken) {
            $currentToken->delete();
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'message' => 'Token rafraîchi avec succès',
        ]);
    }
}
