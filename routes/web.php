<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
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

    Route::inertia('/blog', 'blog/Index')->name('blog.index');
    Route::inertia('/blog/import-postman-collections', 'blog/ImportPostmanCollections')->name('blog.import-postman-collections');
    Route::inertia('/blog/how-to-test-a-rest-api', 'blog/HowToTestARestApi')->name('blog.how-to-test-a-rest-api');
    Route::inertia('/blog/environment-variables-explained', 'blog/EnvironmentVariablesExplained')->name('blog.environment-variables-explained');
}

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
