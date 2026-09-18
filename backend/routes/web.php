<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;

Route::get('/', function () {
    return redirect('/spa/');
});

Route::name('login')->get('/login', function () {
    return redirect('/spa/');
});

Route::get('/spa/{any?}', function ($any = null) {
    $file = public_path('spa/' . ($any ?: 'index.html'));
    if ($any && File::exists($file) && !is_dir($file)) {
        return response()->file($file);
    }
    return response()->file(public_path('spa/index.html'));
})->where('any', '.*');
