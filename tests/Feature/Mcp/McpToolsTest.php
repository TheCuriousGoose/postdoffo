<?php

namespace Tests\Feature\Mcp;

use App\Enums\BodyType;
use App\Enums\HttpMethod;
use App\Enums\WorkspaceRole;
use App\Mcp\Servers\PostdoffoServer;
use App\Mcp\Tools;
use App\Models\Collection;
use App\Models\Environment;
use App\Models\Request as ApiRequest;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use Tests\TestCase;

class McpToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_workspaces_returns_only_workspaces_the_user_belongs_to(): void
    {
        $user = User::factory()->create();
        $owned = Workspace::factory()->create(['owner_id' => $user->id, 'name' => 'Mine']);
        $stranger = Workspace::factory()->create(['name' => 'Not mine']);

        $response = PostdoffoServer::actingAs($user)->tool(Tools\ListWorkspaces::class);

        $response->assertOk()->assertSee('Mine')->assertDontSee('Not mine');

        $this->assertSame([$owned->id], array_column($this->data($response)['workspaces'], 'id'));
        $this->assertDatabaseHas('workspaces', ['id' => $stranger->id]);
    }

    public function test_shared_workspace_is_listed_with_the_members_role(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($editor, ['role' => WorkspaceRole::Editor->value]);

        $data = $this->data(PostdoffoServer::actingAs($editor)->tool(Tools\ListWorkspaces::class));

        $this->assertSame('editor', $data['workspaces'][0]['your_role']);
    }

    public function test_get_workspace_returns_the_nested_collection_tree(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $root = Collection::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Root']);
        $child = Collection::factory()->create([
            'workspace_id' => $workspace->id,
            'parent_id' => $root->id,
            'name' => 'Nested',
        ]);
        ApiRequest::factory()->create(['collection_id' => $child->id, 'name' => 'Ping']);

        $data = $this->data(
            PostdoffoServer::actingAs($user)->tool(Tools\GetWorkspace::class, ['workspace_id' => $workspace->id])
        );

        $this->assertCount(1, $data['collections']);
        $this->assertSame('Root', $data['collections'][0]['name']);
        $this->assertSame('Nested', $data['collections'][0]['children'][0]['name']);
        $this->assertSame('Ping', $data['collections'][0]['children'][0]['requests'][0]['name']);
    }

    public function test_a_stranger_cannot_read_a_workspace_they_do_not_belong_to(): void
    {
        $stranger = User::factory()->create();
        $workspace = Workspace::factory()->create();

        PostdoffoServer::actingAs($stranger)
            ->tool(Tools\GetWorkspace::class, ['workspace_id' => $workspace->id])
            ->assertHasErrors();
    }

    public function test_a_viewer_cannot_create_a_collection(): void
    {
        $viewer = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($viewer, ['role' => WorkspaceRole::Viewer->value]);

        PostdoffoServer::actingAs($viewer)
            ->tool(Tools\CreateCollection::class, ['workspace_id' => $workspace->id, 'name' => 'Nope'])
            ->assertHasErrors();

        $this->assertDatabaseMissing('collections', ['name' => 'Nope']);
    }

    public function test_an_editor_can_build_out_a_collection_and_a_request(): void
    {
        $editor = User::factory()->create();
        $workspace = Workspace::factory()->create();
        $workspace->members()->attach($editor, ['role' => WorkspaceRole::Editor->value]);

        $collection = $this->data(PostdoffoServer::actingAs($editor)->tool(Tools\CreateCollection::class, [
            'workspace_id' => $workspace->id,
            'name' => 'Billing',
        ]))['collection'];

        $request = $this->data(PostdoffoServer::actingAs($editor)->tool(Tools\CreateRequest::class, [
            'collection_id' => $collection['id'],
            'name' => 'List invoices',
            'method' => 'GET',
            'url' => '{{base_url}}/invoices',
            'headers' => [['key' => 'Accept', 'value' => 'application/json']],
            'test_script' => 'pm.test("status is 200", pm.response.status == 200)',
        ]))['request'];

        $this->assertDatabaseHas('collections', ['id' => $collection['id'], 'name' => 'Billing']);
        $this->assertSame('{{base_url}}/invoices', $request['url']);
        $this->assertSame('GET', $request['method']);
        $this->assertSame('Accept', $request['headers'][0]['key']);
        $this->assertStringContainsString('pm.test', (string) $request['test_script']);
    }

    public function test_update_request_only_touches_the_fields_it_is_given(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);
        $apiRequest = ApiRequest::factory()->create([
            'collection_id' => $collection->id,
            'name' => 'Original',
            'url' => 'https://api.example.com/thing',
            'method' => HttpMethod::Get,
        ]);

        PostdoffoServer::actingAs($user)->tool(Tools\UpdateRequest::class, [
            'request_id' => $apiRequest->id,
            'test_script' => 'pm.test("ok", pm.response.status == 200)',
        ])->assertOk();

        $apiRequest->refresh();

        $this->assertSame('Original', $apiRequest->name);
        $this->assertSame('https://api.example.com/thing', $apiRequest->url);
        $this->assertSame(HttpMethod::Get, $apiRequest->method);
        $this->assertStringContainsString('pm.test', (string) $apiRequest->test_script);
    }

    public function test_a_request_cannot_be_moved_into_another_workspaces_collection(): void
    {
        $user = User::factory()->create();
        $mine = Workspace::factory()->create(['owner_id' => $user->id]);
        $other = Workspace::factory()->create(['owner_id' => $user->id]);
        $source = Collection::factory()->create(['workspace_id' => $mine->id]);
        $target = Collection::factory()->create(['workspace_id' => $other->id]);
        $apiRequest = ApiRequest::factory()->create(['collection_id' => $source->id]);

        PostdoffoServer::actingAs($user)->tool(Tools\UpdateRequest::class, [
            'request_id' => $apiRequest->id,
            'collection_id' => $target->id,
        ])->assertHasErrors();

        $this->assertSame($source->id, $apiRequest->fresh()->collection_id);
    }

    public function test_a_collection_cannot_be_moved_into_its_own_subfolder(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $parent = Collection::factory()->create(['workspace_id' => $workspace->id]);
        $child = Collection::factory()->create(['workspace_id' => $workspace->id, 'parent_id' => $parent->id]);

        PostdoffoServer::actingAs($user)->tool(Tools\UpdateCollection::class, [
            'collection_id' => $parent->id,
            'parent_id' => $child->id,
        ])->assertHasErrors();

        $this->assertNull($parent->fresh()->parent_id);
    }

    public function test_deleting_a_workspace_requires_the_name_to_match(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id, 'name' => 'Production APIs']);

        PostdoffoServer::actingAs($user)->tool(Tools\DeleteWorkspace::class, [
            'workspace_id' => $workspace->id,
            'confirm_name' => 'Production API',
        ])->assertHasErrors();

        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id]);

        PostdoffoServer::actingAs($user)->tool(Tools\DeleteWorkspace::class, [
            'workspace_id' => $workspace->id,
            'confirm_name' => 'Production APIs',
        ])->assertOk();

        $this->assertDatabaseMissing('workspaces', ['id' => $workspace->id]);
    }

    public function test_secret_variable_values_are_withheld_from_tool_output(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        $environment = $this->data(PostdoffoServer::actingAs($user)->tool(Tools\CreateEnvironment::class, [
            'workspace_id' => $workspace->id,
            'name' => 'Staging',
            'variables' => [
                ['key' => 'base_url', 'value' => 'https://staging.example.com'],
                ['key' => 'token', 'value' => 'super-secret-value', 'is_secret' => true],
            ],
        ]))['environment'];

        $byKey = collect($environment['variables'])->keyBy('key');

        $this->assertSame('https://staging.example.com', $byKey['base_url']['value']);
        $this->assertNull($byKey['token']['value']);
        $this->assertTrue($byKey['token']['is_secret']);

        // Withheld from the transcript, but genuinely stored: the request that
        // references {{token}} still resolves it.
        $this->assertDatabaseCount('environment_variables', 2);
        $this->assertSame(
            'super-secret-value',
            Environment::find($environment['id'])->variables()->where('key', 'token')->first()->value,
        );
    }

    public function test_setting_environment_variables_leaves_untouched_keys_alone(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $environment = Environment::factory()->create(['workspace_id' => $workspace->id]);
        $environment->variables()->create(['key' => 'keep_me', 'value' => 'original']);

        PostdoffoServer::actingAs($user)->tool(Tools\SetEnvironmentVariables::class, [
            'environment_id' => $environment->id,
            'variables' => [['key' => 'added', 'value' => 'new']],
        ])->assertOk();

        $this->assertSame('original', $environment->variables()->where('key', 'keep_me')->first()->value);
        $this->assertSame('new', $environment->variables()->where('key', 'added')->first()->value);
    }

    public function test_creating_a_request_from_a_curl_command(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);

        $request = $this->data(PostdoffoServer::actingAs($user)->tool(Tools\CreateRequestFromCurl::class, [
            'collection_id' => $collection->id,
            'command' => 'curl -X POST https://api.example.com/orders -H "Content-Type: application/json" -d \'{"sku":"abc"}\'',
        ]))['request'];

        $this->assertSame('POST', $request['method']);
        $this->assertSame('https://api.example.com/orders', $request['url']);
    }

    public function test_execute_request_reports_the_response_and_the_test_results(): void
    {
        Http::fake([
            'api.example.com/*' => Http::response(['status' => 'ok'], 200),
        ]);

        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);
        $apiRequest = ApiRequest::factory()->create([
            'collection_id' => $collection->id,
            'method' => HttpMethod::Get,
            'url' => 'https://api.example.com/health',
            'test_script' => "pm.test(\"status is 200\", pm.response.status == 200)\npm.test(\"is a teapot\", pm.response.status == 418)",
        ]);

        $data = $this->data(PostdoffoServer::actingAs($user)->tool(Tools\ExecuteRequest::class, [
            'request_id' => $apiRequest->id,
        ]));

        $this->assertSame(200, $data['response']['status']);
        $this->assertTrue($data['response']['ok']);
        $this->assertSame(1, $data['response']['tests']['passed']);
        $this->assertSame(1, $data['response']['tests']['failed']);
        $this->assertDatabaseCount('request_history', 1);
    }

    public function test_run_collection_carries_variables_from_one_request_to_the_next(): void
    {
        Http::fake([
            'api.example.com/login' => Http::response(['access_token' => 'token-from-login'], 200),
            'api.example.com/me' => Http::response(['ok' => true], 200),
        ]);

        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);

        ApiRequest::factory()->create([
            'collection_id' => $collection->id,
            'name' => 'Login',
            'order' => 0,
            'method' => HttpMethod::Post,
            'url' => 'https://api.example.com/login',
            'body_type' => BodyType::None,
            'test_script' => 'pm.environment.set("token", pm.response.json.access_token)',
        ]);

        ApiRequest::factory()->create([
            'collection_id' => $collection->id,
            'name' => 'Me',
            'order' => 1,
            'method' => HttpMethod::Get,
            'url' => 'https://api.example.com/me',
            'body_type' => BodyType::None,
            'headers' => [['key' => 'Authorization', 'value' => 'Bearer {{token}}']],
            'test_script' => 'pm.test("status is 200", pm.response.status == 200)',
        ]);

        $data = $this->data(PostdoffoServer::actingAs($user)->tool(Tools\RunCollection::class, [
            'collection_id' => $collection->id,
        ]));

        $this->assertSame(2, $data['summary']['requests_run']);
        $this->assertSame(0, $data['summary']['tests_failed']);

        // The token the login stored is what the second request authenticated with.
        Http::assertSent(fn ($request) => $request->url() === 'https://api.example.com/me'
            && $request->hasHeader('Authorization', 'Bearer token-from-login'));
    }

    public function test_run_collection_can_stop_at_the_first_failure(): void
    {
        Http::fake([
            'api.example.com/one' => Http::response([], 500),
            'api.example.com/two' => Http::response([], 200),
        ]);

        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);

        ApiRequest::factory()->create([
            'collection_id' => $collection->id,
            'order' => 0,
            'method' => HttpMethod::Get,
            'url' => 'https://api.example.com/one',
            'body_type' => BodyType::None,
            'test_script' => 'pm.test("status is 200", pm.response.status == 200)',
        ]);

        ApiRequest::factory()->create([
            'collection_id' => $collection->id,
            'order' => 1,
            'method' => HttpMethod::Get,
            'url' => 'https://api.example.com/two',
            'body_type' => BodyType::None,
        ]);

        $data = $this->data(PostdoffoServer::actingAs($user)->tool(Tools\RunCollection::class, [
            'collection_id' => $collection->id,
            'stop_on_failure' => true,
        ]));

        $this->assertSame(1, $data['summary']['requests_run']);
        $this->assertTrue($data['summary']['stopped_on_failure']);
        Http::assertNotSent(fn ($request) => $request->url() === 'https://api.example.com/two');
    }

    public function test_history_from_another_workspace_is_not_readable(): void
    {
        Http::fake(['api.example.com/*' => Http::response([], 200)]);

        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);
        $apiRequest = ApiRequest::factory()->create([
            'collection_id' => $collection->id,
            'method' => HttpMethod::Get,
            'url' => 'https://api.example.com/health',
            'body_type' => BodyType::None,
        ]);

        PostdoffoServer::actingAs($owner)
            ->tool(Tools\ExecuteRequest::class, ['request_id' => $apiRequest->id])
            ->assertOk();

        $historyId = $workspace->requestHistory()->firstOrFail()->id;

        PostdoffoServer::actingAs($stranger)
            ->tool(Tools\GetRequestHistoryEntry::class, ['history_id' => $historyId])
            ->assertHasErrors();
    }

    /**
     * The structured content a tool call produced, as a plain array.
     *
     * Read through the package's own assertion so the test exercises the same
     * payload a client receives, rather than reaching into internals.
     *
     * @return array<string, mixed>
     */
    private function data(TestResponse $response): array
    {
        $data = [];

        $response->assertStructuredContent(function (AssertableJson $json) use (&$data): void {
            $data = $json->toArray();

            // The fluent assertion otherwise insists every property was
            // interacted with; here it is only being used to read the payload.
            $json->etc();
        });

        return $data;
    }
}
