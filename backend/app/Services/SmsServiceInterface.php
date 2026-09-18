<?php

namespace App\Services;

interface SmsServiceInterface
{
    public function sendVerificationCode(string $phone, string $code): bool;
    public function sendOtp(string $phone, string $otp): bool;
    public function sendWelcomePassword(string $phone, string $name, string $password, ?string $pinCode = null): bool;
}
