<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;

trait InvalidatesDashboardCache {

    public static function bootInvalidatesDashboardCache()
    {
        static::created(fn($model) => self::invalidateDashboardCache());
        static::updated(fn($model) => self::invalidateDashboardCache());
        static::deleted(fn($model) => self::invalidateDashboardCache());
        static::restored(fn($model) => self::invalidateDashboardCache());
    }

    private static function invalidateDashboardCache()
    {
        $keys = [
            'dashboard:admin:*',
            'dashboard:hr:*',
            'dashboard:teacher:*',
            'dashboard:accountant:*',
            'dashboard:academic:*',
        ];

        foreach ($keys as $pattern) {
            Cache::tags(['dashboard'])->flush();
        }
    }
}
