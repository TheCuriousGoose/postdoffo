<?php

namespace Tests\Feature\Settings;

use App\Mcp\McpScopes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Token;
use Tests\TestCase;

class McpSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_mcp_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('mcp.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Mcp')
                ->where('serverUrl', url('/mcp'))
                ->where('tokens', [])
                ->where('connectedApps', [])
                ->where('newToken', null)
                ->etc(),
            );
    }

    public function test_the_mcp_page_is_behind_password_confirmation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('mcp.edit'))
            ->assertRedirect(route('password.confirm'));
    }

    public function test_a_guest_cannot_reach_the_mcp_page(): void
    {
        $this->get(route('mcp.edit'))->assertRedirect(route('login'));
    }

    public function test_creating_a_token_returns_the_plaintext_exactly_once(): void
    {
        $this->createPersonalAccessClient();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('mcp.tokens.store'), ['name' => 'Claude Code'])
            ->assertRedirect();

        $this->assertDatabaseCount('oauth_access_tokens', 1);

        // First load hands over the plaintext...
        $this->actingAs($user)
            ->withSession([
                'auth.password_confirmed_at' => time(),
                'mcp' => ['new_token' => ['name' => 'Claude Code', 'value' => 'plaintext', 'read_only' => false]],
            ])
            ->get(route('mcp.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('newToken.value', 'plaintext')
                ->etc(),
            );

        // ...and the next one does not, because it was only ever flashed.
        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('mcp.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('newToken', null)->etc());
    }

    public function test_a_token_can_be_issued_read_only(): void
    {
        $this->createPersonalAccessClient();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('mcp.tokens.store'), ['name' => 'Look only', 'read_only' => true])
            ->assertRedirect();

        $token = Token::firstOrFail();

        $this->assertSame([McpScopes::READ], $token->scopes);
    }

    public function test_a_token_can_be_revoked(): void
    {
        $this->createPersonalAccessClient();

        $user = User::factory()->create();
        $token = $user->createToken('Revoke me', [McpScopes::USE]);

        $this->actingAs($user)
            ->delete(route('mcp.tokens.destroy', $token->token->getKey()))
            ->assertRedirect();

        $this->assertTrue(Token::findOrFail($token->token->getKey())->revoked);
    }

    public function test_a_user_cannot_revoke_someone_elses_token(): void
    {
        $this->createPersonalAccessClient();

        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $token = $owner->createToken('Not yours', [McpScopes::USE]);

        $this->actingAs($stranger)
            ->delete(route('mcp.tokens.destroy', $token->token->getKey()))
            ->assertNotFound();

        $this->assertFalse(Token::findOrFail($token->token->getKey())->revoked);
    }

    public function test_the_page_explains_itself_when_personal_access_tokens_are_unavailable(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('mcp.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('personalAccessTokensAvailable', false)
                ->etc(),
            );
    }

    public function test_the_oauth_consent_screen_uses_the_app_theme(): void
    {
        $user = User::factory()->create();
        $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            'Some Assistant',
            ['https://example.com/callback'],
            confidential: false,
        );

        $response = $this->actingAs($user)->get(route('passport.authorizations.authorize', [
            'client_id' => $client->getKey(),
            'redirect_uri' => 'https://example.com/callback',
            'response_type' => 'code',
            'scope' => McpScopes::USE,
            'state' => 'abc',
            'code_challenge' => str_repeat('a', 43),
            'code_challenge_method' => 'S256',
        ]));

        $response->assertOk();

        $html = $response->getContent();

        // The published, restyled view rather than the package's stock one: our
        // own copy, our favicon, our compiled stylesheet and our theme tokens —
        // and none of the package's CDN font loading.
        $this->assertStringContainsString('Connect Some Assistant?', $html);
        $this->assertStringContainsString('/favicon.svg', $html);
        $this->assertStringContainsString('/build/assets/app-', $html);
        $this->assertStringContainsString('bg-card text-card-foreground', $html);
        $this->assertStringNotContainsString('fonts.bunny.net', $html);

        // The scope description a user reads before approving is the one this
        // app defines, not a generic "use available MCP functionality".
        $this->assertStringContainsString(McpScopes::all()[McpScopes::USE], $html);
    }

    /**
     * Personal access tokens are signed by a dedicated OAuth client that a real
     * installation creates once with `passport:client --personal`.
     */
    private function createPersonalAccessClient(): void
    {
        app(ClientRepository::class)->createPersonalAccessGrantClient('Test personal access client');
    }
}
