<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\User as SocialUser;
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
     * Handle the OAuth provider's callback. For a guest, this logs the user
     * in (or registers a new account). For an already-authenticated user, it
     * instead treats the round trip as a reauthentication and, if it matches
     * the account's linked provider, satisfies password confirmation without
     * touching the session's login state.
     */
    public function callback(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::configuredProviders(), true), 404);

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (Throwable) {
            $message = 'We could not authenticate you with '.ucfirst($provider).'. Please try again.';

            return request()->user()
                ? redirect()->route('password.confirm')->withErrors(['provider' => $message])
                : redirect()->route('login')->withErrors(['email' => $message]);
        }

        if ($authenticatedUser = request()->user()) {
            return $this->confirmPasswordViaProvider($authenticatedUser, $provider, $socialUser);
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

    /**
     * Confirm an already-logged-in user's password via their linked provider
     * rather than a password they may not know (e.g. because they always
     * sign in through SSO). Never logs anyone in or links a provider to an
     * account — a mismatch just fails the confirmation.
     */
    private function confirmPasswordViaProvider(User $user, string $provider, SocialUser $socialUser): RedirectResponse
    {
        if ($user->provider !== $provider || $user->provider_id !== $socialUser->getId()) {
            return redirect()->route('password.confirm')->withErrors([
                'provider' => 'That '.ucfirst($provider).' account does not match your account.',
            ]);
        }

        request()->session()->put('auth.password_confirmed_at', now()->timestamp);

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
