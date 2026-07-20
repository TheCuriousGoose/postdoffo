<?php

namespace Tests\Feature;

use App\Actions\ExecuteRequestAction;
use App\Enums\BodyType;
use App\Enums\HttpMethod;
use App\Enums\WorkspaceRole;
use App\Models\Collection;
use App\Models\Environment;
use App\Models\Request;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportCollectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_a_postman_v21_collection_with_nested_folders_and_scripts(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        $postman = [
            'info' => ['name' => 'Demo API', 'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json'],
            'variable' => [['key' => 'base_url', 'value' => 'https://api.example.com']],
            'item' => [
                [
                    'name' => 'Users',
                    'item' => [
                        [
                            'name' => 'Get User',
                            'request' => [
                                'method' => 'GET',
                                'header' => [['key' => 'Accept', 'value' => 'application/json']],
                                'url' => [
                                    'raw' => '{{base_url}}/users/1?verbose=true',
                                    'query' => [['key' => 'verbose', 'value' => 'true']],
                                ],
                            ],
                            'event' => [
                                ['listen' => 'test', 'script' => ['exec' => ['pm.test("status is 200", pm.response.status == 200)']]],
                            ],
                        ],
                        [
                            'name' => 'Create User',
                            'request' => [
                                'method' => 'post',
                                'header' => [],
                                'url' => '{{base_url}}/users',
                                'body' => [
                                    'mode' => 'raw',
                                    'raw' => '{"name": "Ada"}',
                                    'options' => ['raw' => ['language' => 'json']],
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'Ping',
                    'request' => [
                        'method' => 'GET',
                        'url' => '{{base_url}}/ping',
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($user)
            ->postJson(route('api.collections.import', $workspace), ['collection' => $postman])
            ->assertOk()
            ->json();

        $this->assertSame('Demo API', $response['name']);
        $this->assertSame(['base_url' => 'https://api.example.com'], $response['variables']);

        $folder = Collection::where('name', 'Users')->firstOrFail();
        $this->assertSame($response['id'], $folder->parent_id);

        $getUser = Request::where('name', 'Get User')->firstOrFail();
        $this->assertSame(HttpMethod::Get, $getUser->method);
        $this->assertSame('{{base_url}}/users/1?verbose=true', $getUser->url);
        $this->assertSame('Accept', $getUser->headers[0]['key']);
        $this->assertSame('verbose', $getUser->query_params[0]['key']);
        $this->assertStringContainsString('pm.test', $getUser->test_script);

        $createUser = Request::where('name', 'Create User')->firstOrFail();
        $this->assertSame(HttpMethod::Post, $createUser->method);
        $this->assertSame(BodyType::Json, $createUser->body_type);
        $this->assertSame(['name' => 'Ada'], $createUser->body['json']);

        $ping = Request::where('name', 'Ping')->firstOrFail();
        $this->assertSame($response['id'], $ping->collection_id);
    }

    public function test_it_creates_an_active_base_environment_from_collection_variables(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        $postman = [
            'info' => ['name' => 'Demo API'],
            'variable' => [
                ['key' => 'base_url', 'value' => 'https://api.example.com'],
                ['key' => 'api_token', 'value' => 'sk_live_123'],
            ],
            'item' => [],
        ];

        $this->actingAs($user)
            ->postJson(route('api.collections.import', $workspace), ['collection' => $postman])
            ->assertOk();

        $environment = Environment::where('workspace_id', $workspace->id)->firstOrFail();
        $this->assertSame('Demo API (base)', $environment->name);
        $this->assertTrue($environment->is_active);

        $variables = $environment->variables()->pluck('is_secret', 'key');
        $this->assertSame('https://api.example.com', $environment->variables()->where('key', 'base_url')->value('value'));
        $this->assertFalse((bool) $variables['base_url']);
        // A credential-looking key is stored as a secret.
        $this->assertTrue((bool) $variables['api_token']);
    }

    public function test_it_does_not_create_an_environment_when_there_are_no_base_variables(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        $this->actingAs($user)
            ->postJson(route('api.collections.import', $workspace), [
                'collection' => ['info' => ['name' => 'No Vars'], 'item' => []],
            ])
            ->assertOk();

        $this->assertSame(0, Environment::where('workspace_id', $workspace->id)->count());
    }

    public function test_an_imported_base_environment_does_not_steal_active_from_an_existing_one(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $existing = $workspace->environments()->create(['name' => 'Existing', 'is_active' => true]);

        $this->actingAs($user)
            ->postJson(route('api.collections.import', $workspace), [
                'collection' => [
                    'info' => ['name' => 'Demo API'],
                    'variable' => [['key' => 'base_url', 'value' => 'https://api.example.com']],
                    'item' => [],
                ],
            ])
            ->assertOk();

        $this->assertTrue($existing->fresh()->is_active);
        $this->assertFalse(
            Environment::where('name', 'Demo API (base)')->firstOrFail()->is_active,
        );
    }

    public function test_viewer_cannot_import_a_collection(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($viewer, ['role' => WorkspaceRole::Viewer->value]);

        $this->actingAs($viewer)
            ->postJson(route('api.collections.import', $workspace), [
                'collection' => ['info' => ['name' => 'x'], 'item' => []],
            ])
            ->assertForbidden();
    }

    public function test_it_imports_a_real_export_with_collection_level_bearer_auth_and_sends_it(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        $postman = json_decode(
            file_get_contents(base_path('storage/app/private/Products.postman_collection.json')),
            true,
        );

        $response = $this->actingAs($user)
            ->postJson(route('api.collections.import', $workspace), ['collection' => $postman])
            ->assertOk()
            ->json();

        $this->assertSame('Products', $response['name']);
        $this->assertSame(
            'https://pim.dev.transoplast-dev.wux.nl/api',
            $response['variables']['base_url'],
        );

        // The collection carries a root-level bearer auth block — it should
        // have been captured as structured auth rather than silently dropped.
        $this->assertSame('bearer', $response['auth_type']);
        $this->assertSame('{{token}}', $response['auth']['token']);

        $this->assertSame(10, Request::where('collection_id', $response['id'])->count());

        $getAllProducts = Request::where('name', 'Get all products')->firstOrFail();
        $this->assertSame(HttpMethod::Get, $getAllProducts->method);
        $this->assertSame('{{base_url}}/products?page=1', $getAllProducts->url);
        $this->assertSame('page', $getAllProducts->query_params[0]['key']);

        Http::fake(['pim.dev.transoplast-dev.wux.nl/*' => Http::response(['data' => []], 200)]);

        app(ExecuteRequestAction::class)->handle($getAllProducts, $user);

        Http::assertSent(function ($sentRequest) {
            return $sentRequest->url() === 'https://pim.dev.transoplast-dev.wux.nl/api/products?page=1'
                && $sentRequest->hasHeader(
                    'Authorization',
                    'Bearer 13|wLlP8dzteoCX2PwfrGjH05J220d2o4JSAZQlj02zee8c3326',
                );
        });
    }

    public function test_it_maps_apikey_auth_in_the_header_location(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        $postman = [
            'info' => ['name' => 'Api Key Demo'],
            'auth' => [
                'type' => 'apikey',
                'apikey' => [
                    ['key' => 'key', 'value' => 'X-API-Key'],
                    ['key' => 'value', 'value' => '{{api_key}}'],
                    ['key' => 'in', 'value' => 'header'],
                ],
            ],
            'item' => [],
        ];

        $response = $this->actingAs($user)
            ->postJson(route('api.collections.import', $workspace), ['collection' => $postman])
            ->assertOk()
            ->json();

        $this->assertSame('apikey', $response['auth_type']);
        $this->assertSame('X-API-Key', $response['auth']['key']);
        $this->assertSame('{{api_key}}', $response['auth']['value']);
        $this->assertSame('header', $response['auth']['in']);
    }

    public function test_it_maps_basic_auth_and_encodes_variables_at_send_time(): void
    {
        // Postman computes the base64 digest at send time from whatever the
        // variables resolve to — baking it in at import would break as soon
        // as either credential contains a {{variable}}. Confirm we do the
        // same: import keeps the raw fields, and encoding happens on send.
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        $postman = [
            'info' => ['name' => 'Basic Auth Demo'],
            'auth' => [
                'type' => 'basic',
                'basic' => [
                    ['key' => 'username', 'value' => '{{username}}'],
                    ['key' => 'password', 'value' => 'secret'],
                ],
            ],
            'item' => [
                [
                    'name' => 'Ping',
                    'request' => ['method' => 'GET', 'url' => 'https://api.example.com/ping'],
                ],
            ],
            'variable' => [['key' => 'username', 'value' => 'admin']],
        ];

        $response = $this->actingAs($user)
            ->postJson(route('api.collections.import', $workspace), ['collection' => $postman])
            ->assertOk()
            ->json();

        $this->assertSame('basic', $response['auth_type']);
        $this->assertSame('{{username}}', $response['auth']['username']);

        Http::fake(['api.example.com/*' => Http::response('pong', 200)]);

        $ping = Request::where('name', 'Ping')->firstOrFail();
        app(ExecuteRequestAction::class)->handle($ping, $user);

        Http::assertSent(fn ($sentRequest) => $sentRequest->hasHeader(
            'Authorization',
            'Basic '.base64_encode('admin:secret'),
        ));
    }

    public function test_an_explicit_request_header_wins_over_an_inherited_auth_header(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        $postman = [
            'info' => ['name' => 'Override Demo'],
            'auth' => [
                'type' => 'bearer',
                'bearer' => [['key' => 'token', 'value' => 'collection-token']],
            ],
            'item' => [
                [
                    'name' => 'Custom Auth Request',
                    'request' => [
                        'method' => 'GET',
                        'header' => [['key' => 'Authorization', 'value' => 'Bearer explicit-token']],
                        'url' => 'https://api.example.com/ping',
                    ],
                ],
            ],
        ];

        $this->actingAs($user)
            ->postJson(route('api.collections.import', $workspace), ['collection' => $postman])
            ->assertOk();

        $request = Request::where('name', 'Custom Auth Request')->firstOrFail();
        $this->assertNull($request->auth_type);
        $this->assertSame('Bearer explicit-token', $request->headers[0]['value']);

        Http::fake(['api.example.com/*' => Http::response('pong', 200)]);

        app(ExecuteRequestAction::class)->handle($request, $user);

        Http::assertSent(fn ($sentRequest) => $sentRequest->hasHeader('Authorization', 'Bearer explicit-token'));
    }

    public function test_explicit_noauth_cancels_inherited_collection_auth(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        $postman = [
            'info' => ['name' => 'Noauth Demo'],
            'auth' => [
                'type' => 'bearer',
                'bearer' => [['key' => 'token', 'value' => 'secret-token']],
            ],
            'item' => [
                [
                    'name' => 'Public Endpoint',
                    'request' => [
                        'method' => 'GET',
                        'auth' => ['type' => 'noauth'],
                        'url' => 'https://api.example.com/public',
                    ],
                ],
            ],
        ];

        $this->actingAs($user)
            ->postJson(route('api.collections.import', $workspace), ['collection' => $postman])
            ->assertOk();

        $request = Request::where('name', 'Public Endpoint')->firstOrFail();
        $this->assertSame('none', $request->auth_type->value);

        Http::fake(['api.example.com/*' => Http::response('ok', 200)]);

        app(ExecuteRequestAction::class)->handle($request, $user);

        Http::assertSent(fn ($sentRequest) => ! $sentRequest->hasHeader('Authorization'));
    }
}
