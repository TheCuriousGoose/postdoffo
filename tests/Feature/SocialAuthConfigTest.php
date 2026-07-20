<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\SocialAuthController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialAuthConfigTest extends TestCase
{
    use RefreshDatabase;

    private function configure(string $provider): void
    {
        config()->set("services.$provider.client_id", 'test-client-id');
        config()->set("services.$provider.client_secret", 'test-client-secret');
        config()->set("services.$provider.redirect", "http://localhost/auth/$provider/callback");
    }

    private function unconfigure(string $provider): void
    {
        config()->set("services.$provider.client_id", null);
        config()->set("services.$provider.client_secret", null);
    }

    public function test_configured_providers_only_lists_providers_with_credentials(): void
    {
        $this->configure('google');
        $this->unconfigure('github');

        $this->assertSame(['google'], SocialAuthController::configuredProviders());
    }

    public function test_redirect_404s_when_the_provider_is_not_configured(): void
    {
        $this->unconfigure('google');

        $this->get(route('auth.social.redirect', ['provider' => 'google']))
            ->assertNotFound();
    }

    public function test_redirect_works_once_the_provider_is_configured(): void
    {
        $this->configure('google');

        $response = $this->get(route('auth.social.redirect', ['provider' => 'google']));

        $response->assertRedirect();
        $this->assertStringContainsString(
            'accounts.google.com',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_callback_404s_when_the_provider_is_not_configured(): void
    {
        $this->unconfigure('github');

        $this->get(route('auth.social.callback', ['provider' => 'github']))
            ->assertNotFound();
    }
}
