<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class SocialAuthController extends Controller
{
    /**
     * The social providers supported for authentication.
     *
     * @var list<string>
     */
    public const PROVIDERS = ['google', 'github'];

    /**
     * The providers that actually have OAuth credentials configured. A provider
     * with no client id/secret is treated as switched off, so its button never
     * appears and its routes 404 rather than blowing up inside Socialite.
     *
     * @return list<string>
     */
    public static function configuredProviders(): array
    {
        return array_values(array_filter(
            self::PROVIDERS,
            fn (string $provider): bool => filled(config("services.$provider.client_id"))
                && filled(config("services.$provider.client_secret")),
        ));
    }

    /**
     * Redirect the user to the given OAuth provider's consent screen.
     */
    public function redirect(string $provider): SymfonyResponse
    {
        abort_unless(in_array($provider, self::configuredProviders(), true), 404);

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Handle the OAuth provider's callback, logging the user in or
     * registering a new account if one doesn't already exist.
     */
    public function callback(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::configuredProviders(), true), 404);

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (Throwable) {
            return redirect()->route('login')->withErrors([
                'email' => 'We could not authenticate you with '.ucfirst($provider).'. Please try again.',
            ]);
        }

        if (! $socialUser->getEmail()) {
            return redirect()->route('login')->withErrors([
                'email' => 'Your '.ucfirst($provider).' account has no verified email address to sign in with.',
            ]);
        }

        $user = User::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if (! $user) {
            $user = User::where('email', $socialUser->getEmail())->first();

            if ($user) {
                $user->forceFill([
                    'provider' => $provider,
                    'provider_id' => $socialUser->getId(),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();
            } else {
                $user = User::create([
                    'name' => $socialUser->getName() ?: $socialUser->getNickname() ?: explode('@', $socialUser->getEmail())[0],
                    'email' => $socialUser->getEmail(),
                    'password' => null,
                    'provider' => $provider,
                    'provider_id' => $socialUser->getId(),
                    'email_verified_at' => now(),
                ]);
            }
        }

        Auth::login($user, remember: true);

        request()->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
