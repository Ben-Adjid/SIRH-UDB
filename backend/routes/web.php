<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;

Route::get('/logo.jpg', function () {
    return response()->file(public_path('spa/logo.jpg'));
});

// SPA fallback (production)
Route::get('/{any?}', function () {
    return response()->file(public_path('spa/index.html'));
})->where('any', '.*');
