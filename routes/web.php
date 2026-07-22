<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::inertia('/privacy-policy', 'legal/Privacy')->name('legal.privacy');
Route::inertia('/terms-of-service', 'legal/Terms')->name('legal.terms');
Route::inertia('/docs/scripting', 'docs/Scripting')->name('docs.scripting');

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

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
