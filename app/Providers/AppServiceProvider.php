<?php

namespace App\Providers;

use App\Mcp\McpScopes;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->configureMcp();
    }

    /**
     * Wire up the OAuth side of the MCP server (routes/ai.php).
     *
     * Assistants authenticate as a real user through Passport, so everything an
     * MCP tool does lands on the same WorkspacePolicy checks the web UI uses —
     * an agent connected to a viewer's account still can't write.
     */
    protected function configureMcp(): void
    {
        Passport::tokensCan(McpScopes::all());

        // Access tokens are short-lived because an MCP client holds one for as
        // long as it's connected; the refresh token is what keeps a long-running
        // session alive, and revoking it in settings actually ends access.
        Passport::tokensExpireIn(now()->addDays(15));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::personalAccessTokensExpireIn(now()->addYear());

        // The consent screen the user sees when an assistant asks for access.
        // resources/views/mcp/authorize.blade.php — laravel/mcp's version
        // published and restyled against this app's own theme tokens, so it
        // reads as part of PostDoffo rather than a stock OAuth interstitial.
        Passport::authorizationView('mcp.authorize');
    }

    /**
     * Every request a user fires is fetched by this server, so an unbounded
     * account is an unbounded traffic generator pointed wherever it likes.
     * Keyed per user rather than per IP: a team behind one office address
     * shouldn't share a budget. The limiter is only ever reached through
     * authenticated routes, so there is always a user to key on.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('execute-request', fn (Request $request) => Limit::perMinute(
            (int) config('requests.rate_limit_per_minute')
        )->by((string) $request->user()->id));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
