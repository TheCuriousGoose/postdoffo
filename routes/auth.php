<?php

use App\Http\Controllers\Auth\SocialAuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->prefix('auth/{provider}')->whereIn('provider', SocialAuthController::PROVIDERS)->group(function () {
    Route::get('redirect', [SocialAuthController::class, 'redirect'])->name('auth.social.redirect');
    Route::get('callback', [SocialAuthController::class, 'callback'])->name('auth.social.callback');
});
