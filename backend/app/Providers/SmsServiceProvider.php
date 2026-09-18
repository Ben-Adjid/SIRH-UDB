<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\SmsServiceInterface;
use App\Services\AfricaTalkingSmsService;
use App\Services\LogSmsService;

class SmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SmsServiceInterface::class, function ($app) {
            $driver = config('services.sms.driver', 'log');

            return match ($driver) {
                'africastalking' => new AfricaTalkingSmsService(),
                default => new LogSmsService(),
            };
        });
    }
}
