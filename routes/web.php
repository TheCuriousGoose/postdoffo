<?php

use App\Http\Controllers\BlogController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Settings\AvatarController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// The 'home' route name is depended on by auth/legal/docs layouts
// (a "back to home" link) regardless of whether marketing is enabled, so
// the route itself always exists — only its content is conditional.
Route::get('/', function () {
    if (! config('marketing.enabled')) {
        return redirect()->route(auth()->check() ? 'dashboard' : 'login');
    }

    return Inertia::render('Welcome');
})->name('home');

if (config('marketing.enabled')) {
    Route::inertia('/vs/postman', 'vs/Postman')->name('vs.postman');
    Route::inertia('/import/postman', 'ImportPostman')->name('import.postman');
    Route::inertia('/self-hosting', 'SelfHosting')->name('self-hosting');

    Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
    Route::get('/blog/{post:slug}', [BlogController::class, 'show'])->name('blog.show');
}

Route::inertia('/privacy-policy', 'legal/Privacy')->name('legal.privacy');
Route::inertia('/terms-of-service', 'legal/Terms')->name('legal.terms');
Route::inertia('/docs/scripting', 'docs/Scripting')->name('docs.scripting');

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

// Profile pictures sit on a private disk, so they're handed out here instead of
// through a public link. Everywhere one is shown is behind a login, and the file
// name changes on every upload, which is what makes the response cacheable forever.
Route::get('avatars/{user}/{file}', [AvatarController::class, 'show'])
    ->middleware('auth')
    ->where('file', '[0-9A-Za-z]+\.jpg')
    ->name('profile.avatar.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', HomeController::class)->name('dashboard');

    Route::prefix('api')->name('api.')->group(function () {
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    });
});

require __DIR__.'/auth.php';
require __DIR__.'/settings.php';
require __DIR__.'/workspaces.php';
require __DIR__.'/admin.php';
