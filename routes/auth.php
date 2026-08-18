<?php

use App\Http\Controllers\Auth\SocialAuthController;
use Illuminate\Support\Facades\Route;

// Neither route is restricted to guests: an already-authenticated user hits
// the same redirect/callback pair to reauthenticate for password confirmation
// (e.g. before minting an MCP token), and the callback tells the two cases
// apart by whether a session is already logged in.
Route::prefix('auth/{provider}')->whereIn('provider', SocialAuthController::PROVIDERS)->group(function () {
    Route::get('redirect', [SocialAuthController::class, 'redirect'])->name('auth.social.redirect');
    Route::get('callback', [SocialAuthController::class, 'callback'])->name('auth.social.callback');
});
