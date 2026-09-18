<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AfricaTalkingSmsService implements SmsServiceInterface
{
    private string $apiKey;
    private string $username;
    private string $from;

    public function __construct()
    {
        $this->apiKey = config('services.africastalking.api_key', '');
        $this->username = config('services.africastalking.username', 'sandbox');
        $this->from = config('services.africastalking.from', '');
    }

    public function sendVerificationCode(string $phone, string $code): bool
    {
        $message = "SIRH Dakar - Votre code de vérification est: {$code}. Valable 10 minutes. Ne partagez ce code avec personne.";
        return $this->send($phone, $message);
    }

    public function sendOtp(string $phone, string $otp): bool
    {
        $message = "SIRH Dakar - Code OTP: {$otp}. Valable 5 minutes.";
        return $this->send($phone, $message);
    }

    public function sendWelcomePassword(string $phone, string $name, string $password, ?string $pinCode = null): bool
    {
        $message = "SIRH Dakar - Bienvenue {$name}! Votre mot de passe temporaire est: {$password}. Connectez-vous et changez-le immediatement.";
        if ($pinCode) {
            $message .= " Votre code PIN de pointage: {$pinCode}.";
        }
        return $this->send($phone, $message);
    }

    private function send(string $phone, string $message): bool
    {
        try {
            if (!$this->apiKey) {
                Log::warning('Africa\'s Talking SMS skipped: API key is not configured');
                return false;
            }

            $response = Http::timeout(config('services.africastalking.timeout', 15))->withHeaders([
                'apiKey' => $this->apiKey,
            ])->asForm()->post(config('services.africastalking.endpoint'), [
                'username' => $this->username,
                'to' => $phone,
                'from' => $this->from,
                'message' => $message,
            ]);

            $body = $response->json();
            $status = $body['SMSMessageData']['Recipients'][0]['status'] ?? '';

            if ($status === 'Success') {
                return true;
            }

            Log::error('Africa\'s Talking SMS failed', ['response' => $body]);
            return false;
        } catch (\Exception $e) {
            Log::error('Africa\'s Talking SMS error', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
