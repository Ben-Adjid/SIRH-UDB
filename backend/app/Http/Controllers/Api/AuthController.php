<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;
use App\Services\BrevoMailService;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum')->except(['register', 'login', 'forgotPassword', 'resetPassword', 'verifyEmail', 'sendVerificationEmail']);
    }

    public static function generateTempPassword(): string
    {
        return 'UDB' . random_int(1000, 9999);
    }

    public function register(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email',
                'phone' => 'nullable|string|max:20',
                'role' => 'required|string|exists:roles,name',
            ]);

            $authUser = auth()->user();
            if ($authUser && !$authUser->hasRole('admin') && $validated['role'] === 'admin') {
                Log::warning('[Auth] Unauthorized admin assignment', ['user_id' => $authUser->id]);
                return response()->json(['success' => false, 'message' => 'Vous ne pouvez pas attribuer le rôle administrateur'], 403);
            }

            $result = app(\App\Services\UserCreationService::class)->createUser($validated, $validated['role']);
            $user = $result['user'];

            Log::info('[Auth] User registered', [
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $validated['role'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Compte créé. Un SMS avec le mot de passe temporaire a été envoyé.',
                'user' => $user,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[Auth] Validation error on register', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\RuntimeException $e) {
            Log::error('[Auth] Runtime error on register', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 503);
        } catch (\Throwable $e) {
            Log::error('[Auth] Register error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function login(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'required|string|email',
                'password' => 'required|string',
            ]);

            $key = 'login_attempts_' . md5($validated['email']);
            $attempts = cache()->get($key, 0);

            if ($attempts >= 5) {
                Log::warning('[Auth] Login rate limit exceeded', ['email' => $validated['email']]);
                return response()->json(['success' => false, 'message' => 'Trop de tentatives. Réessayez dans 5 minutes.'], 429);
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
                        'model_id' => $user->id,
                        'ip_address' => $request->getClientIp(),
                        'user_agent' => $request->userAgent(),
                    ]);

                    Log::warning('[Auth] Login failed - invalid password', [
                        'user_id' => $user->id,
                        'attempt' => $attempts + 1,
                    ]);
                }

                return response()->json(['success' => false, 'message' => 'Identifiants invalides'], 401);
            }

            if ($user->status !== 'active') {
                Log::warning('[Auth] Login attempt on inactive account', ['user_id' => $user->id]);
                return response()->json(['success' => false, 'message' => 'Compte désactivé'], 403);
            }

            $requires2fa = !is_null($user->two_factor_enabled_at);

            if ($requires2fa) {
                $tempToken = Str::random(64);
                $tempTokenKey = '2fa_temp_token_' . md5($user->email);
                cache()->put($tempTokenKey, Hash::make($tempToken), now()->addMinutes(5));
                cache()->forget($key);

                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'login_2fa_pending',
                    'model' => 'User',
                    'model_id' => $user->id,
                    'ip_address' => $request->getClientIp(),
                    'user_agent' => $request->userAgent(),
                ]);

                Log::info('[Auth] 2FA required', ['user_id' => $user->id]);

                return response()->json([
                    'success' => true,
                    'message' => 'Code 2FA requis',
                    'requires_2fa' => true,
                    'email_verified' => !is_null($user->email_verified_at),
                    'token' => $tempToken,
                ], 200);
            }

            $token = $user->createToken('auth_token')->plainTextToken;
            $user->update(['last_login_at' => now()]);
            cache()->forget($key);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'login_success',
                'model' => 'User',
                'model_id' => $user->id,
                'ip_address' => $request->getClientIp(),
                'user_agent' => $request->userAgent(),
            ]);

            Log::info('[Auth] Login success', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Connexion réussie',
                'user' => $user->load('roles', 'teacher'),
                'token' => $token,
                'email_verified' => !is_null($user->email_verified_at),
                'requires_2fa' => false,
                'must_change_password' => (bool) $user->must_change_password,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[Auth] Validation error on login', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[Auth] Login error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function me(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            Log::debug('[Auth] Me requested', ['user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'data' => $user->load('roles', 'teacher'),
            ]);
        } catch (\Throwable $e) {
            Log::error('[Auth] Me error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user) {
                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'logout',
                    'model' => 'User',
                    'model_id' => $user->id,
                    'ip_address' => $request->getClientIp(),
                    'user_agent' => $request->userAgent(),
                ]);

                $user->tokens()->delete();

                Log::info('[Auth] Logout success', ['user_id' => $user->id]);
            }

            return response()->json(['success' => true, 'message' => 'Déconnecté']);
        } catch (\Throwable $e) {
            Log::error('[Auth] Logout error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
                'phone' => 'nullable|string|max:20',
                'attendance_pin' => 'nullable|digits_between:4,6',
            ]);

            if (isset($validated['name'])) {
                $user->name = $validated['name'];
            }

            if (isset($validated['email']) && $validated['email'] !== $user->email) {
                $user->email = $validated['email'];
                $user->email_verified_at = null;
            }

            if (isset($validated['phone']) && $validated['phone'] !== $user->phone) {
                $user->phone = $validated['phone'];
                $user->phone_verified_at = null;
            }

            if (array_key_exists('attendance_pin', $validated)) {
                $pinValue = $validated['attendance_pin'] ?: null;
                $user->attendance_pin = $pinValue;

                if ($user->hasRole('teacher')) {
                    $teacher = \App\Models\Teacher::where('user_id', $user->id)->first();
                    if ($teacher) {
                        $teacher->update(['pin_code' => $pinValue]);
                    }
                }
            }

            $user->save();

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'update_profile',
                'model' => 'User',
                'model_id' => $user->id,
                'ip_address' => $request->getClientIp(),
            ]);

            Log::info('[Auth] Profile updated', ['user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'Profil mis à jour',
                'data' => $user->fresh()->load('roles', 'teacher'),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[Auth] Validation error on updateProfile', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[Auth] updateProfile error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function changePassword(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'current_password' => 'required|string',
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

            if (!Hash::check($validated['current_password'], $user->password)) {
                Log::warning('[Auth] changePassword - incorrect current password', ['user_id' => $user->id]);
                return response()->json(['success' => false, 'message' => 'Mot de passe actuel incorrect'], 422);
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
                'ip_address' => $request->getClientIp(),
            ]);

            Log::info('[Auth] Password changed', ['user_id' => $user->id]);

            return response()->json(['success' => true, 'message' => 'Mot de passe changé']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[Auth] Validation error on changePassword', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[Auth] changePassword error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function forceChangePassword(Request $request): JsonResponse
    {
        try {
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
                Log::warning('[Auth] forceChangePassword not required', ['user_id' => $user->id]);
                return response()->json(['success' => false, 'message' => 'Vous n\'êtes pas obligé de changer votre mot de passe.'], 422);
            }

            $user->update([
                'password' => Hash::make($validated['new_password']),
                'must_change_password' => false,
            ]);

            $user->tokens()->delete();
            cache()->forget('2fa_temp_token_' . md5($user->email));
            $newToken = $user->createToken('auth_token')->plainTextToken;

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'force_change_password',
                'model' => 'User',
                'model_id' => $user->id,
                'ip_address' => $request->getClientIp(),
            ]);

            Log::info('[Auth] Force password changed', ['user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe changé avec succès.',
                'data' => $user->fresh()->load('roles', 'teacher'),
                'token' => $newToken,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[Auth] Validation error on forceChangePassword', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[Auth] forceChangePassword error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'required|string|email',
            ]);

            $user = User::where('email', $validated['email'])->first();

            if (!$user) {
                Log::debug('[Auth] Forgot password for non-existent user', ['email' => $validated['email']]);
                return response()->json(['success' => true, 'message' => 'Si cette adresse email est associée à un compte, vous recevrez un lien de réinitialisation.']);
            }

            $key = 'forgot_password_' . md5($validated['email']);
            $attempts = cache()->get($key, 0);

            if ($attempts >= 3) {
                Log::warning('[Auth] Forgot password rate limit', ['email' => $validated['email']]);
                return response()->json(['success' => false, 'message' => 'Trop de demandes. Réessayez dans 15 minutes.'], 429);
            }

            $token = Str::random(64);

            DB::table('password_reset_tokens')->updateOrInsert(
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
                Log::error('[Auth] Brevo API error on forgotPassword', ['error' => $e->getMessage()]);
            }

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'forgot_password',
                'model' => 'User',
                'model_id' => $user->id,
                'ip_address' => $request->getClientIp(),
            ]);

            Log::info('[Auth] Forgot password requested', ['user_id' => $user->id]);

            return response()->json(['success' => true, 'message' => 'Si cette adresse email est associée à un compte, vous recevrez un lien de réinitialisation.']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[Auth] Validation error on forgotPassword', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[Auth] forgotPassword error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function resetPassword(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'token' => 'required|string',
                'email' => 'required|string|email',
                'password' => [
                    'required',
                    'string',
                    'min:12',
                    'confirmed',
                    'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]{12,}$/',
                ],
            ]);

            $resetToken = DB::table('password_reset_tokens')
                ->where('email', $validated['email'])
                ->first();

            if (!$resetToken || !Hash::check($validated['token'], $resetToken->token)) {
                Log::warning('[Auth] resetPassword - invalid token', ['email' => $validated['email']]);
                return response()->json(['success' => false, 'message' => 'Lien de réinitialisation invalide ou expiré.'], 422);
            }

            if (now()->diffInMinutes($resetToken->created_at) > 60) {
                DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();
                Log::warning('[Auth] resetPassword - token expired', ['email' => $validated['email']]);
                return response()->json(['success' => false, 'message' => 'Lien de réinitialisation expiré. Demandez un nouveau lien.'], 422);
            }

            $user = User::where('email', $validated['email'])->first();

            if (!$user) {
                Log::warning('[Auth] resetPassword - user not found', ['email' => $validated['email']]);
                return response()->json(['success' => false, 'message' => 'Utilisateur non trouvé.'], 404);
            }

            $user->update(['password' => Hash::make($validated['password'])]);
            $user->tokens()->delete();
            DB::table('password_reset_tokens')->where('email', $validated['email'])->delete();

            event(new PasswordReset($user));

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'reset_password',
                'model' => 'User',
                'model_id' => $user->id,
                'ip_address' => $request->getClientIp(),
            ]);

            Log::info('[Auth] Password reset successful', ['user_id' => $user->id]);

            return response()->json(['success' => true, 'message' => 'Mot de passe réinitialisé avec succès. Connectez-vous avec votre nouveau mot de passe.']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[Auth] Validation error on resetPassword', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[Auth] resetPassword error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function sendVerificationEmail(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->hasVerifiedEmail()) {
                Log::debug('[Auth] Email already verified', ['user_id' => $user->id]);
                return response()->json(['success' => true, 'message' => 'Votre adresse email est déjà vérifiée.']);
            }

            $key = 'verify_email_' . $user->id;
            $attempts = cache()->get($key, 0);

            if ($attempts >= 3) {
                Log::warning('[Auth] sendVerificationEmail rate limit', ['user_id' => $user->id]);
                return response()->json(['success' => false, 'message' => 'Trop de demandes. Réessayez dans 15 minutes.'], 429);
            }

            $token = Str::random(64);

            DB::table('email_verification_tokens')->updateOrInsert(
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
                Log::error('[Auth] Brevo API error on sendVerificationEmail', ['error' => $e->getMessage()]);
            }

            Log::info('[Auth] Verification email sent', ['user_id' => $user->id]);

            return response()->json(['success' => true, 'message' => 'Un email de vérification a été envoyé.']);
        } catch (\Throwable $e) {
            Log::error('[Auth] sendVerificationEmail error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'token' => 'required|string',
                'email' => 'required|string|email',
            ]);

            $verificationToken = DB::table('email_verification_tokens')
                ->where('email', $validated['email'])
                ->first();

            if (!$verificationToken || !Hash::check($validated['token'], $verificationToken->token)) {
                Log::warning('[Auth] verifyEmail - invalid token', ['email' => $validated['email']]);
                return response()->json(['success' => false, 'message' => 'Lien de vérification invalide.'], 422);
            }

            if (now()->diffInHours($verificationToken->created_at) > 24) {
                DB::table('email_verification_tokens')->where('email', $validated['email'])->delete();
                Log::warning('[Auth] verifyEmail - token expired', ['email' => $validated['email']]);
                return response()->json(['success' => false, 'message' => 'Lien de vérification expiré. Demandez un nouveau lien.'], 422);
            }

            $user = User::where('email', $validated['email'])->first();

            if (!$user) {
                Log::warning('[Auth] verifyEmail - user not found', ['email' => $validated['email']]);
                return response()->json(['success' => false, 'message' => 'Utilisateur non trouvé.'], 404);
            }

            $user->update(['email_verified_at' => now()]);
            DB::table('email_verification_tokens')->where('email', $validated['email'])->delete();

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'email_verified',
                'model' => 'User',
                'model_id' => $user->id,
            ]);

            Log::info('[Auth] Email verified', ['user_id' => $user->id]);

            return response()->json(['success' => true, 'message' => 'Email vérifié avec succès.']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[Auth] Validation error on verifyEmail', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[Auth] verifyEmail error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function refreshToken(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                Log::warning('[Auth] refreshToken - not authenticated');
                return response()->json(['success' => false, 'message' => 'Non authentifié'], 401);
            }

            $currentToken = $user->currentAccessToken();
            if ($currentToken) {
                $currentToken->delete();
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            Log::info('[Auth] Token refreshed', ['user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'token' => $token,
                'message' => 'Token rafraîchi avec succès',
            ]);
        } catch (\Throwable $e) {
            Log::error('[Auth] refreshToken error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }
}
