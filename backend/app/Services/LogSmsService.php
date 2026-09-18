<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class LogSmsService implements SmsServiceInterface
{
    public function sendVerificationCode(string $phone, string $code): bool
    {
        Log::info('SMS verification code requested', ['phone' => $phone]);
        return true;
    }

    public function sendOtp(string $phone, string $otp): bool
    {
        Log::info('SMS OTP requested', ['phone' => $phone]);
        return true;
    }

    public function sendWelcomePassword(string $phone, string $name, string $password, ?string $pinCode = null): bool
    {
        Log::info('SMS welcome password requested', [
            'phone' => $phone,
            'name' => $name,
            'pin_code' => $pinCode,
        ]);
        return true;
    }
}
