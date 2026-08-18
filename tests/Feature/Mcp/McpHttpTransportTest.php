<?php

namespace Tests\Feature\Mcp;

use App\Mcp\McpScopes;
use App\Mcp\Servers\PostdoffoServer;
use App\Mcp\Tools;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class McpHttpTransportTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_mcp_endpoint_rejects_an_unauthenticated_request(): void
    {
        $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
            'params' => [],
        ])->assertUnauthorized();
    }

    public function test_the_mcp_endpoint_answers_an_authenticated_request(): void
    {
        Passport::actingAs(User::factory()->create(), [McpScopes::USE]);

        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => [],
                'clientInfo' => ['name' => 'test', 'version' => '1'],
            ],
        ]);

        $response->assertOk();
        $this->assertStringContainsString('PostDoffo', $response->getContent());
    }

    public function test_only_post_is_accepted(): void
    {
        $this->get('/mcp')->assertStatus(405);
        $this->delete('/mcp')->assertStatus(405);
    }

    public function test_the_oauth_discovery_documents_advertise_this_server(): void
    {
        $this->getJson('/.well-known/oauth-protected-resource')
            ->assertOk()
            ->assertJsonPath('scopes_supported', [McpScopes::USE]);

        $this->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJsonPath('token_endpoint', route('passport.token'))
            ->assertJsonPath('code_challenge_methods_supported', ['S256']);
    }

    public function test_a_read_only_token_can_read(): void
    {
        $user = User::factory()->create();
        Workspace::factory()->create(['owner_id' => $user->id, 'name' => 'Readable']);

        Passport::actingAs($user, [McpScopes::READ]);

        PostdoffoServer::tool(Tools\ListWorkspaces::class)
            ->assertOk()
            ->assertSee('Readable');
    }

    public function test_a_read_only_token_cannot_write_even_for_an_owner(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        Passport::actingAs($user, [McpScopes::READ]);

        PostdoffoServer::tool(Tools\CreateCollection::class, [
            'workspace_id' => $workspace->id,
            'name' => 'Should not exist',
        ])->assertHasErrors();

        $this->assertDatabaseMissing('collections', ['name' => 'Should not exist']);
    }

    public function test_a_read_only_token_cannot_send_requests(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        Passport::actingAs($user, [McpScopes::READ]);

        // Creating the workspace to run against is itself refused, which is the
        // point: nothing on the write path is reachable with this scope.
        PostdoffoServer::tool(Tools\CreateEnvironment::class, [
            'workspace_id' => $workspace->id,
            'name' => 'Staging',
        ])->assertHasErrors();

        $this->assertDatabaseCount('environments', 0);
    }

    public function test_a_full_access_token_can_write(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        Passport::actingAs($user, [McpScopes::USE]);

        PostdoffoServer::tool(Tools\CreateCollection::class, [
            'workspace_id' => $workspace->id,
            'name' => 'Allowed',
        ])->assertOk();

        $this->assertDatabaseHas('collections', ['name' => 'Allowed']);
    }
}
