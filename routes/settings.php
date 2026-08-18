<?php

use App\Http\Controllers\Settings\AvatarController;
use App\Http\Controllers\Settings\McpController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Decoding and re-encoding a picture costs real CPU, so uploads are rate
    // limited even though they only ever come from the user's own profile page.
    Route::post('settings/profile/avatar', [AvatarController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('profile.avatar.store');

    Route::delete('settings/profile/avatar', [AvatarController::class, 'destroy'])->name('profile.avatar.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware('password.confirm')
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');

    // Handing an assistant a token is handing it the account, so the page that
    // mints one sits behind the same password confirmation as the security page.
    Route::get('settings/mcp', [McpController::class, 'edit'])
        ->middleware('password.confirm')
        ->name('mcp.edit');

    Route::post('settings/mcp/tokens', [McpController::class, 'storeToken'])
        ->middleware(['password.confirm', 'throttle:6,1'])
        ->name('mcp.tokens.store');

    Route::delete('settings/mcp/tokens/{token}', [McpController::class, 'destroyToken'])->name('mcp.tokens.destroy');
    Route::delete('settings/mcp/apps/{client}', [McpController::class, 'destroyApp'])->name('mcp.apps.destroy');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
