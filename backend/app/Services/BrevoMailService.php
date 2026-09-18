<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class BrevoMailService
{
    public function send(string $to, string $subject, string $htmlContent, ?string $toName = null): bool
    {
        $apiKey = config('services.brevo.api_key');
        if (!$apiKey) {
            Log::error('Brevo API key not configured');
            return false;
        }

        $payload = [
            'sender' => [
                'email' => config('mail.from.address'),
                'name' => config('mail.from.name'),
            ],
            'to' => [
                ['email' => $to, 'name' => $toName ?? $to],
            ],
            'subject' => $subject,
            'htmlContent' => $htmlContent,
        ];

        $ch = curl_init(config('services.brevo.endpoint'));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'api-key: ' . $apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => (int) config('services.brevo.timeout', 15),
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        if ($httpCode === 201 || $httpCode === 200) {
            curl_close($ch);
            Log::info('Brevo email sent successfully', ['to' => $to, 'subject' => $subject]);
            return true;
        }

        curl_close($ch);
        Log::error('Brevo email failed', [
            'to' => $to,
            'http_code' => $httpCode,
            'response' => $response,
            'error' => $error,
        ]);
        return false;
    }

    public function sendWelcomeEmail(User $user, string $tempPassword, ?string $pinCode = null): bool
    {
        $loginUrl = config('app.url') . '/login';
        $html = view('emails.auth.welcome', [
            'user' => $user,
            'tempPassword' => $tempPassword,
            'pinCode' => $pinCode,
        ])->render();

        return $this->send(
            $user->email,
            'Bienvenue sur SIRH Dakar — Votre compte est prêt',
            $html,
            $user->name
        );
    }

    public function sendResetPasswordLink(User $user, string $token, string $email): bool
    {
        $resetUrl = config('app.url') . '/reset-password?token=' . $token . '&email=' . urlencode($email);
        $html = view('emails.auth.reset-password', [
            'user' => $user,
            'resetUrl' => $resetUrl,
            'email' => $email,
        ])->render();

        return $this->send(
            $user->email,
            'Réinitialisation de votre mot de passe — SIRH Dakar',
            $html,
            $user->name
        );
    }

    public function sendVerificationEmail(User $user, string $token, string $email): bool
    {
        $verifyUrl = config('app.url') . '/verify-email?token=' . $token . '&email=' . urlencode($email);
        $html = view('emails.auth.verify-email', [
            'user' => $user,
            'verifyUrl' => $verifyUrl,
            'email' => $email,
        ])->render();

        return $this->send(
            $user->email,
            'Vérification de votre adresse email — SIRH Dakar',
            $html,
            $user->name
        );
    }

    public function sendLeaveStatusUpdate(User $user, array $leaveData): bool
    {
        $html = view('emails.leaves.status-updated', [
            'user' => $user,
            'leave' => $leaveData,
        ])->render();

        return $this->send(
            $user->email,
            'Mise à jour de votre demande de congé — SIRH Dakar',
            $html,
            $user->name
        );
    }
}
