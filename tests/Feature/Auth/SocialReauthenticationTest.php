<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class SocialReauthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function configure(string $provider): void
    {
        config()->set("services.$provider.client_id", 'test-client-id');
        config()->set("services.$provider.client_secret", 'test-client-secret');
    }

    private function mockSocialiteUser(string $provider, string|int $providerId): void
    {
        $driver = $this->mock(SocialiteProvider::class);
        $driver->shouldReceive('user')->andReturn(SocialiteUser::fake(['id' => $providerId]));

        Socialite::shouldReceive('driver')->with($provider)->andReturn($driver);
    }

    public function test_reauthenticating_with_the_linked_provider_confirms_password_without_logging_out(): void
    {
        $this->configure('google');

        $user = User::factory()->create([
            'password' => bcrypt('a-forgotten-password'),
            'provider' => 'google',
            'provider_id' => 'google-123',
        ]);

        $this->mockSocialiteUser('google', 'google-123');

        $response = $this->actingAs($user)
            ->get(route('auth.social.callback', ['provider' => 'google']));

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertTrue(session()->has('auth.password_confirmed_at'));
        $this->assertAuthenticatedAs($user);
    }

    /**
     * GitHub returns the account id as a JSON number, so Socialite hands back an
     * int while the column hands back a string. Comparing the two strictly used
     * to reject every GitHub reauthentication.
     */
    public function test_reauthenticating_with_a_numeric_provider_id_confirms_password(): void
    {
        $this->configure('github');

        $user = User::factory()->create([
            'password' => bcrypt('a-forgotten-password'),
            'provider' => 'github',
            'provider_id' => '58217291',
        ]);

        $this->mockSocialiteUser('github', 58217291);

        $response = $this->actingAs($user)
            ->get(route('auth.social.callback', ['provider' => 'github']));

        $response->assertRedirect(route('dashboard', absolute: false));
        $response->assertSessionHasNoErrors();
        $this->assertTrue(session()->has('auth.password_confirmed_at'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_reauthenticating_with_a_different_numeric_account_does_not_confirm_password(): void
    {
        $this->configure('github');

        $user = User::factory()->create([
            'password' => bcrypt('a-forgotten-password'),
            'provider' => 'github',
            'provider_id' => '58217291',
        ]);

        $this->mockSocialiteUser('github', 99999999);

        $response = $this->actingAs($user)
            ->get(route('auth.social.callback', ['provider' => 'github']));

        $response->assertRedirect(route('password.confirm'));
        $response->assertSessionHasErrors('provider');
        $this->assertFalse(session()->has('auth.password_confirmed_at'));
    }

    public function test_a_provider_that_returns_no_identity_does_not_confirm_password(): void
    {
        $this->configure('github');

        $user = User::factory()->create([
            'password' => bcrypt('a-forgotten-password'),
            'provider' => 'github',
            'provider_id' => '58217291',
        ]);

        $this->mockSocialiteUser('github', '');

        $response = $this->actingAs($user)
            ->get(route('auth.social.callback', ['provider' => 'github']));

        $response->assertRedirect(route('password.confirm'));
        $response->assertSessionHasErrors('provider');
        $this->assertFalse(session()->has('auth.password_confirmed_at'));
    }

    public function test_reauthenticating_with_a_mismatched_provider_identity_does_not_confirm_password(): void
    {
        $this->configure('google');

        $user = User::factory()->create([
            'password' => bcrypt('a-forgotten-password'),
            'provider' => 'google',
            'provider_id' => 'google-123',
        ]);

        $this->mockSocialiteUser('google', 'a-different-account');

        $response = $this->actingAs($user)
            ->get(route('auth.social.callback', ['provider' => 'google']));

        $response->assertRedirect(route('password.confirm'));
        $response->assertSessionHasErrors('provider');
        $this->assertFalse(session()->has('auth.password_confirmed_at'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_reauthenticating_with_no_linked_provider_on_the_account_does_not_confirm_password(): void
    {
        $this->configure('google');

        $user = User::factory()->create([
            'password' => bcrypt('a-forgotten-password'),
            'provider' => null,
            'provider_id' => null,
        ]);

        $this->mockSocialiteUser('google', 'google-123');

        $response = $this->actingAs($user)
            ->get(route('auth.social.callback', ['provider' => 'google']));

        $response->assertRedirect(route('password.confirm'));
        $response->assertSessionHasErrors('provider');
        $this->assertFalse(session()->has('auth.password_confirmed_at'));
    }

    public function test_reauthenticating_does_not_link_the_provider_to_a_different_account(): void
    {
        $this->configure('google');

        $user = User::factory()->create([
            'password' => bcrypt('a-forgotten-password'),
            'provider' => null,
            'provider_id' => null,
        ]);

        $this->mockSocialiteUser('google', 'google-123');

        $this->actingAs($user)->get(route('auth.social.callback', ['provider' => 'google']));

        $this->assertNull($user->fresh()->provider);
        $this->assertNull($user->fresh()->provider_id);
    }
}
