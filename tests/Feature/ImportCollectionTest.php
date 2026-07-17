<?php

namespace Tests\Feature;

use App\Enums\BodyType;
use App\Enums\HttpMethod;
use App\Enums\WorkspaceRole;
use App\Models\Collection;
use App\Models\Request;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
