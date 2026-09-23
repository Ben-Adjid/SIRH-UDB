<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;
use App\Services\SmsServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    private Google2FA $google2fa;
    private SmsServiceInterface $smsService;

    private const CACHE_TTL = 600;
    private const MAX_ATTEMPTS = 5;
    private const RATE_LIMIT_WINDOW = 300;

    public function __construct(SmsServiceInterface $smsService)
    {
        $this->google2fa = new Google2FA();
        $this->smsService = $smsService;
        $this->middleware('auth:sanctum')->except(['verifyLogin', 'verifyWithRecoveryCode']);
    }

    public function getSecret(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->two_factor_enabled_at) {
                Log::warning('[2FA] Already enabled attempt', ['user_id' => $user->id]);
                return response()->json(['success' => false, 'message' => 'La double authentification est déjà activée.'], 422);
            }

            $key = '2fa_get_secret_' . $user->id;
            $attempts = cache()->get($key, 0);

            if ($attempts >= self::MAX_ATTEMPTS) {
                Log::warning('[2FA] Rate limit exceeded on getSecret', [
                    'user_id' => $user->id,
                    'attempts' => $attempts,
                ]);
                return response()->json(['success' => false, 'message' => 'Trop de demandes. Réessayez dans 5 minutes.'], 429);
            }

            $secret = $this->google2fa->generateSecretKey();
            $user->update(['two_factor_secret' => $secret]);

            cache()->put($key, $attempts + 1, now()->addMinutes(5));

            $qrCodeUrl = $this->google2fa->getQRCodeUrl(
                config('app.name'),
                $user->email,
                $secret
            );

            Log::info('[2FA] Secret generated', ['user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'secret' => $secret,
                'qr_code_url' => $qrCodeUrl,
            ]);
        } catch (\Throwable $e) {
            Log::error('[2FA] getSecret error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function enable(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'code' => 'required|string|size:6',
            ]);

            $user = $request->user();

            if ($user->two_factor_enabled_at) {
                Log::warning('[2FA] Enable attempt when already enabled', ['user_id' => $user->id]);
                return response()->json(['success' => false, 'message' => 'La double authentification est déjà activée.'], 422);
            }

            if (!$user->two_factor_secret) {
                Log::warning('[2FA] Enable without secret', ['user_id' => $user->id]);
                return response()->json(['success' => false, 'message' => 'Générez d\'abord un secret avec GET /auth/2fa/secret.'], 422);
            }

            $valid = $this->google2fa->verifyKey(
                $user->two_factor_secret,
                $validated['code'],
                1
            );

            if (!$valid) {
                Log::warning('[2FA] Enable verification failed - invalid code', ['user_id' => $user->id]);
                return response()->json(['success' => false, 'message' => 'Code invalide. Vérifiez votre application d\'authentification.'], 422);
            }

            $recoveryCodes = $this->generateRecoveryCodes();

            $user->update([
                'two_factor_enabled_at' => now(),
                'two_factor_recovery_codes' => json_encode($this->hashRecoveryCodes($recoveryCodes)),
            ]);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => '2fa_enabled',
                'model' => 'User',
                'model_id' => $user->id,
                'ip_address' => $request->getClientIp(),
                'user_agent' => $request->userAgent(),
            ]);

            Log::info('[2FA] Enabled successfully', [
                'user_id' => $user->id,
                'ip' => $request->getClientIp(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Double authentification activée avec succès.',
                'recovery_codes' => $recoveryCodes,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[2FA] Validation error on enable', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[2FA] Enable error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function disable(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'password' => 'required|string',
            ]);

            $user = $request->user();

            if (!$user->two_factor_enabled_at) {
                Log::warning('[2FA] Disable attempt when not enabled', ['user_id' => $user->id]);
                return response()->json(['success' => false, 'message' => 'La double authentification n\'est pas activée.'], 422);
            }

            if (!Hash::check($validated['password'], $user->password)) {
                Log::warning('[2FA] Disable with incorrect password', [
                    'user_id' => $user->id,
                    'ip' => $request->getClientIp(),
                ]);
                return response()->json(['success' => false, 'message' => 'Mot de passe incorrect.'], 422);
            }

            $user->update([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_enabled_at' => null,
            ]);

            $user->tokens()->delete();

            AuditLog::create([
                'user_id' => $user->id,
                'action' => '2fa_disabled',
                'model' => 'User',
                'model_id' => $user->id,
                'ip_address' => $request->getClientIp(),
                'user_agent' => $request->userAgent(),
            ]);

            Log::info('[2FA] Disabled successfully', [
                'user_id' => $user->id,
                'ip' => $request->getClientIp(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Double authentification désactivée.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[2FA] Validation error on disable', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[2FA] Disable error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function verifyLogin(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'token' => 'required|string',
                'email' => 'required|email',
                'code' => 'required|string|size:6',
            ]);

            $tempTokenKey = '2fa_temp_token_' . md5($validated['email']);
            $storedTokenHash = cache()->get($tempTokenKey);

            if (!$storedTokenHash || !Hash::check($validated['token'], $storedTokenHash)) {
                Log::warning('[2FA] verifyLogin - invalid or expired session', [
                    'email' => $validated['email'],
                    'ip' => $request->getClientIp(),
                ]);
                return response()->json(['success' => false, 'message' => 'Session 2FA invalide ou expirée. Reconnectez-vous.'], 422);
            }

            $attemptsKey = '2fa_attempts_' . md5($validated['email']);
            $attempts = cache()->get($attemptsKey, 0);

            if ($attempts >= self::MAX_ATTEMPTS) {
                Log::warning('[2FA] verifyLogin - rate limit exceeded', [
                    'email' => $validated['email'],
                    'attempts' => $attempts,
                    'ip' => $request->getClientIp(),
                ]);
                return response()->json(['success' => false, 'message' => 'Trop de tentatives. Réessayez dans 5 minutes.'], 429);
            }

            $user = User::where('email', $validated['email'])->first();

            if (!$user || !$user->two_factor_enabled_at) {
                Log::warning('[2FA] verifyLogin - user not found or 2FA disabled', [
                    'email' => $validated['email'],
                    'user_found' => $user ? true : false,
                ]);
                return response()->json(['success' => false, 'message' => 'Utilisateur non trouvé ou 2FA non activé.'], 422);
            }

            $valid = $this->google2fa->verifyKey(
                $user->two_factor_secret,
                $validated['code'],
                1
            );

            if (!$valid) {
                cache()->put($attemptsKey, $attempts + 1, now()->addMinutes(5));

                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => '2fa_login_failed',
                    'model' => 'User',
                    'model_id' => $user->id,
                    'ip_address' => $request->getClientIp(),
                    'user_agent' => $request->userAgent(),
                ]);

                Log::warning('[2FA] verifyLogin - invalid code', [
                    'user_id' => $user->id,
                    'attempt' => $attempts + 1,
                    'ip' => $request->getClientIp(),
                ]);

                return response()->json(['success' => false, 'message' => 'Code 2FA invalide.'], 422);
            }

            cache()->forget($tempTokenKey);
            cache()->forget($attemptsKey);

            $newToken = $user->createToken('auth_token')->plainTextToken;
            $user->update(['last_login_at' => now()]);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'login_2fa_success',
                'model' => 'User',
                'model_id' => $user->id,
                'ip_address' => $request->getClientIp(),
                'user_agent' => $request->userAgent(),
            ]);

            Log::info('[2FA] verifyLogin - success', [
                'user_id' => $user->id,
                'ip' => $request->getClientIp(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Authentification 2FA réussie.',
                'user' => $user->load('roles', 'teacher'),
                'token' => $newToken,
                'must_change_password' => (bool) $user->must_change_password,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[2FA] verifyLogin validation error', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[2FA] verifyLogin error', [
                'error' => $e->getMessage(),
                'ip' => $request->getClientIp(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function verifyWithRecoveryCode(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'token' => 'required|string',
                'email' => 'required|email',
                'code' => 'required|string|min:8|max:8',
            ]);

            $tempTokenKey = '2fa_temp_token_' . md5($validated['email']);
            $storedTokenHash = cache()->get($tempTokenKey);

            if (!$storedTokenHash || !Hash::check($validated['token'], $storedTokenHash)) {
                Log::warning('[2FA] verifyWithRecoveryCode - invalid session', [
                    'email' => $validated['email'],
                    'ip' => $request->getClientIp(),
                ]);
                return response()->json(['success' => false, 'message' => 'Session 2FA invalide ou expirée.'], 422);
            }

            $attemptsKey = '2fa_recovery_attempts_' . md5($validated['email']);
            $attempts = cache()->get($attemptsKey, 0);

            if ($attempts >= self::MAX_ATTEMPTS) {
                Log::warning('[2FA] verifyWithRecoveryCode - rate limit', [
                    'email' => $validated['email'],
                    'attempts' => $attempts,
                    'ip' => $request->getClientIp(),
                ]);
                return response()->json(['success' => false, 'message' => 'Trop de tentatives. Réessayez dans 5 minutes.'], 429);
            }

            $user = User::where('email', $validated['email'])->first();

            if (!$user || !$user->two_factor_enabled_at || !$user->two_factor_recovery_codes) {
                Log::warning('[2FA] verifyWithRecoveryCode - invalid user state', [
                    'email' => $validated['email'],
                    'user_found' => $user ? true : false,
                ]);
                return response()->json(['success' => false, 'message' => 'Utilisateur non trouvé ou 2FA non activé.'], 422);
            }

            $hashedCodes = json_decode($user->two_factor_recovery_codes, true);
            if (!is_array($hashedCodes)) {
                Log::warning('[2FA] verifyWithRecoveryCode - no recovery codes', ['user_id' => $user->id]);
                return response()->json(['success' => false, 'message' => 'Aucun code de récupération disponible.'], 422);
            }

            $found = false;
            $foundIndex = -1;
            foreach ($hashedCodes as $index => $hashedCode) {
                if (Hash::check($validated['code'], $hashedCode)) {
                    $found = true;
                    $foundIndex = $index;
                    break;
                }
            }

            if (!$found) {
                cache()->put($attemptsKey, $attempts + 1, now()->addMinutes(5));

                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => '2fa_recovery_failed',
                    'model' => 'User',
                    'model_id' => $user->id,
                    'ip_address' => $request->getClientIp(),
                    'user_agent' => $request->userAgent(),
                ]);

                Log::warning('[2FA] verifyWithRecoveryCode - invalid code', [
                    'user_id' => $user->id,
                    'attempt' => $attempts + 1,
                    'ip' => $request->getClientIp(),
                ]);

                return response()->json(['success' => false, 'message' => 'Code de récupération invalide.'], 422);
            }

            unset($hashedCodes[$foundIndex]);
            $user->update(['two_factor_recovery_codes' => json_encode(array_values($hashedCodes))]);

            cache()->forget($tempTokenKey);
            cache()->forget($attemptsKey);

            $newToken = $user->createToken('auth_token')->plainTextToken;
            $user->update(['last_login_at' => now()]);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'login_2fa_recovery_success',
                'model' => 'User',
                'model_id' => $user->id,
                'ip_address' => $request->getClientIp(),
                'user_agent' => $request->userAgent(),
            ]);

            $remainingCodes = count(array_values($hashedCodes));

            Log::info('[2FA] verifyWithRecoveryCode - success', [
                'user_id' => $user->id,
                'remaining_codes' => $remainingCodes,
                'ip' => $request->getClientIp(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Authentification par code de récupération réussie.',
                'user' => $user->load('roles', 'teacher'),
                'token' => $newToken,
                'remaining_recovery_codes' => $remainingCodes,
                'must_change_password' => (bool) $user->must_change_password,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[2FA] verifyWithRecoveryCode validation error', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[2FA] verifyWithRecoveryCode error', [
                'error' => $e->getMessage(),
                'ip' => $request->getClientIp(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function sendPhoneVerification(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'phone' => 'required|string|max:20',
            ]);

            $user = $request->user();

            if ($user->phone_verified_at && $user->phone === $validated['phone']) {
                Log::debug('[2FA] Phone already verified', ['user_id' => $user->id]);
                return response()->json(['success' => false, 'message' => 'Ce numéro est déjà vérifié.'], 422);
            }

            $key = 'phone_verify_' . $user->id;
            $attempts = cache()->get($key, 0);

            if ($attempts >= 3) {
                Log::warning('[2FA] Phone verification rate limit', [
                    'user_id' => $user->id,
                    'attempts' => $attempts,
                ]);
                return response()->json(['success' => false, 'message' => 'Trop de demandes. Réessayez dans 10 minutes.'], 429);
            }

            $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

            cache()->put('phone_code_' . $user->id, [
                'code' => Hash::make($code),
                'phone' => $validated['phone'],
            ], now()->addMinutes(10));

            cache()->put($key, $attempts + 1, now()->addMinutes(10));

            $phone = $validated['phone'];
            if (!str_starts_with($phone, '+')) {
                $phone = preg_replace('/^0/', '', $phone);
                if (!str_starts_with($phone, '221')) {
                    $phone = '221' . $phone;
                }
                $phone = '+' . $phone;
            }

            try {
                $this->smsService->sendVerificationCode($phone, $code);
            } catch (\Throwable $e) {
                Log::error('[2FA] SMS service error', [
                    'error' => $e->getMessage(),
                    'user_id' => $user->id,
                    'phone' => $phone,
                ]);
                return response()->json(['success' => false, 'message' => 'Erreur lors de l\'envoi du SMS'], 500);
            }

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'phone_verification_sent',
                'model' => 'User',
                'model_id' => $user->id,
                'ip_address' => $request->getClientIp(),
            ]);

            Log::info('[2FA] Phone verification code sent', [
                'user_id' => $user->id,
                'phone_masked' => $this->maskPhone($validated['phone']),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Un code de vérification a été envoyé par SMS.',
                'phone_masked' => $this->maskPhone($validated['phone']),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[2FA] sendPhoneVerification validation error', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[2FA] sendPhoneVerification error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    public function verifyPhone(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'code' => 'required|string|size:6',
            ]);

            $user = $request->user();
            $cacheData = cache()->get('phone_code_' . $user->id);

            if (!$cacheData) {
                Log::warning('[2FA] verifyPhone - code expired', ['user_id' => $user->id]);
                return response()->json(['success' => false, 'message' => 'Code expiré. Demandez un nouveau code.'], 422);
            }

            if (!Hash::check($validated['code'], $cacheData['code'])) {
                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'phone_verification_failed',
                    'model' => 'User',
                    'model_id' => $user->id,
                    'ip_address' => $request->getClientIp(),
                ]);

                Log::warning('[2FA] verifyPhone - invalid code', [
                    'user_id' => $user->id,
                    'ip' => $request->getClientIp(),
                ]);

                return response()->json(['success' => false, 'message' => 'Code invalide.'], 422);
            }

            $user->update([
                'phone' => $cacheData['phone'],
                'phone_verified_at' => now(),
            ]);

            cache()->forget('phone_code_' . $user->id);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'phone_verified',
                'model' => 'User',
                'model_id' => $user->id,
                'ip_address' => $request->getClientIp(),
            ]);

            Log::info('[2FA] Phone verified successfully', [
                'user_id' => $user->id,
                'phone_masked' => $this->maskPhone($cacheData['phone']),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Numéro de téléphone vérifié avec succès.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('[2FA] verifyPhone validation error', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('[2FA] verifyPhone error', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);
            return response()->json(['success' => false, 'message' => 'Erreur serveur'], 500);
        }
    }

    private function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4)));
        }
        return $codes;
    }

    private function hashRecoveryCodes(array $codes): array
    {
        return array_map(fn ($code) => Hash::make($code), $codes);
    }

    private function maskPhone(string $phone): string
    {
        if (strlen($phone) <= 4) return $phone;
        return substr($phone, 0, 3) . '***' . substr($phone, -2);
    }
}
