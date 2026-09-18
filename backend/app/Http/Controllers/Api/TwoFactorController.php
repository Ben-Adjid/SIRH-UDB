<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AuditLog;
use App\Services\SmsServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    private Google2FA $google2fa;
    private SmsServiceInterface $smsService;

    public function __construct(SmsServiceInterface $smsService)
    {
        $this->google2fa = new Google2FA();
        $this->smsService = $smsService;
    }

    public function getSecret(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->two_factor_enabled_at) {
            return response()->json(['message' => 'La double authentification est déjà activée.'], 422);
        }

        $key = '2fa_get_secret_' . $user->id;
        $attempts = cache()->get($key, 0);
        if ($attempts >= 5) {
            return response()->json(['message' => 'Trop de demandes. Réessayez dans 5 minutes.'], 429);
        }

        $secret = $this->google2fa->generateSecretKey();
        $user->update(['two_factor_secret' => $secret]);

        cache()->put($key, $attempts + 1, now()->addMinutes(5));

        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        return response()->json([
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
        ]);
    }

    public function enable(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();

        if ($user->two_factor_enabled_at) {
            return response()->json(['message' => 'La double authentification est déjà activée.'], 422);
        }

        if (!$user->two_factor_secret) {
            return response()->json(['message' => 'Générez d\'abord un secret avec GET /auth/2fa/secret.'], 422);
        }

        $valid = $this->google2fa->verifyKey(
            $user->two_factor_secret,
            $validated['code'],
            1
        );

        if (!$valid) {
            return response()->json(['message' => 'Code invalide. Vérifiez votre application d\'authentification.'], 422);
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
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'Double authentification activée avec succès.',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    public function disable(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => 'required',
        ]);

        $user = $request->user();

        if (!$user->two_factor_enabled_at) {
            return response()->json(['message' => 'La double authentification n\'est pas activée.'], 422);
        }

        if (!Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Mot de passe incorrect.'], 422);
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
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(['message' => 'Double authentification désactivée.']);
    }

    public function verifyLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        $tempTokenKey = '2fa_temp_token_' . md5($validated['email']);
        $storedTokenHash = cache()->get($tempTokenKey);

        if (!$storedTokenHash || !Hash::check($validated['token'], $storedTokenHash)) {
            return response()->json(['message' => 'Session 2FA invalide ou expirée. Reconnectez-vous.'], 422);
        }

        $attemptsKey = '2fa_attempts_' . md5($validated['email']);
        $attempts = cache()->get($attemptsKey, 0);
        if ($attempts >= 5) {
            return response()->json(['message' => 'Trop de tentatives. Réessayez dans 5 minutes.'], 429);
        }

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !$user->two_factor_enabled_at) {
            return response()->json(['message' => 'Utilisateur non trouvé ou 2FA non activé.'], 422);
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
                'action' => '2fa_failed',
                'model' => 'User',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json(['message' => 'Code 2FA invalide.'], 422);
        }

        cache()->forget($tempTokenKey);
        cache()->forget($attemptsKey);

        $newToken = $user->createToken('auth_token')->plainTextToken;

        $user->update(['last_login_at' => now()]);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'login_2fa',
            'model' => 'User',
            'model_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'Authentification 2FA réussie.',
            'user' => $user->load('roles', 'teacher'),
            'token' => $newToken,
            'must_change_password' => (bool) $user->must_change_password,
        ]);
    }

    public function verifyWithRecoveryCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'code' => 'required|string|min:8|max:8',
        ]);

        $tempTokenKey = '2fa_temp_token_' . md5($validated['email']);
        $storedTokenHash = cache()->get($tempTokenKey);

        if (!$storedTokenHash || !Hash::check($validated['token'], $storedTokenHash)) {
            return response()->json(['message' => 'Session 2FA invalide ou expirée.'], 422);
        }

        $attemptsKey = '2fa_recovery_attempts_' . md5($validated['email']);
        $attempts = cache()->get($attemptsKey, 0);
        if ($attempts >= 5) {
            return response()->json(['message' => 'Trop de tentatives. Réessayez dans 5 minutes.'], 429);
        }

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !$user->two_factor_enabled_at || !$user->two_factor_recovery_codes) {
            return response()->json(['message' => 'Utilisateur non trouvé ou 2FA non activé.'], 422);
        }

        $hashedCodes = json_decode($user->two_factor_recovery_codes, true);
        if (!is_array($hashedCodes)) {
            return response()->json(['message' => 'Aucun code de récupération disponible.'], 422);
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
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            return response()->json(['message' => 'Code de récupération invalide.'], 422);
        }

        unset($hashedCodes[$foundIndex]);
        $user->update(['two_factor_recovery_codes' => json_encode(array_values($hashedCodes))]);

        cache()->forget($tempTokenKey);
        cache()->forget($attemptsKey);

        $newToken = $user->createToken('auth_token')->plainTextToken;
        $user->update(['last_login_at' => now()]);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'login_2fa_recovery',
            'model' => 'User',
            'model_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $remainingCodes = count(array_values($hashedCodes));

        return response()->json([
            'message' => 'Authentification par code de récupération réussie.',
            'user' => $user->load('roles', 'teacher'),
            'token' => $newToken,
            'remaining_recovery_codes' => $remainingCodes,
            'must_change_password' => (bool) $user->must_change_password,
        ]);
    }

    public function sendPhoneVerification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string|max:20',
        ]);

        $user = $request->user();

        if ($user->phone_verified_at && $user->phone === $validated['phone']) {
            return response()->json(['message' => 'Ce numéro est déjà vérifié.'], 422);
        }

        $key = 'phone_verify_' . $user->id;
        $attempts = cache()->get($key, 0);
        if ($attempts >= 3) {
            return response()->json(['message' => 'Trop de demandes. Réessayez dans 10 minutes.'], 429);
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

        $this->smsService->sendVerificationCode($phone, $code);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'phone_verification_sent',
            'model' => 'User',
            'model_id' => $user->id,
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Un code de vérification a été envoyé par SMS.',
            'phone_masked' => $this->maskPhone($validated['phone']),
        ]);
    }

    public function verifyPhone(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();

        $cacheData = cache()->get('phone_code_' . $user->id);

        if (!$cacheData) {
            return response()->json(['message' => 'Code expiré. Demandez un nouveau code.'], 422);
        }

        if (!Hash::check($validated['code'], $cacheData['code'])) {
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'phone_verification_failed',
                'model' => 'User',
                'ip_address' => $request->ip(),
            ]);
            return response()->json(['message' => 'Code invalide.'], 422);
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
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Numéro de téléphone vérifié avec succès.']);
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
