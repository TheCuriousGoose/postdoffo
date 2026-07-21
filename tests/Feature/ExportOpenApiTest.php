<?php

namespace Tests\Feature;

use App\Enums\AuthType;
use App\Enums\BodyType;
use App\Enums\HttpMethod;
use App\Models\Request;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportOpenApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exports_a_collection_as_an_openapi_document(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $collection = $workspace->collections()->create(['name' => 'Demo API', 'order' => 0]);

        Request::factory()->create([
            'collection_id' => $collection->id,
            'name' => 'Get User',
            'method' => HttpMethod::Get,
            'url' => '{{base_url}}/users/{{id}}',
            'headers' => [['key' => 'Accept', 'value' => 'application/json']],
            'query_params' => [['key' => 'verbose', 'value' => 'true']],
            'body_type' => BodyType::None,
            'auth_type' => AuthType::Bearer,
            'auth' => ['token' => '{{token}}'],
        ]);

        $export = $this->actingAs($user)
            ->get(route('api.collections.download', $collection).'?format=openapi')
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename="demo-api.openapi.json"')
            ->json();

        $this->assertSame('3.0.3', $export['openapi']);
        $this->assertSame('Demo API', $export['info']['title']);
        $this->assertSame([['url' => '{base_url}', 'variables' => ['base_url' => ['default' => '']]]], $export['servers']);

        $operation = $export['paths']['/users/{id}']['get'];
        $this->assertSame('Get User', $operation['summary']);
        $this->assertSame(['200' => ['description' => 'Successful response']], $operation['responses']);

        $paramsByName = array_combine(array_column($operation['parameters'], 'name'), array_column($operation['parameters'], 'in'));
        $this->assertSame(['id' => 'path', 'verbose' => 'query', 'Accept' => 'header'], $paramsByName);

        $this->assertSame([['bearerAuth' => []]], $operation['security']);
        $this->assertSame(['type' => 'http', 'scheme' => 'bearer'], $export['components']['securitySchemes']['bearerAuth']);
    }

    public function test_it_maps_json_body_and_form_data_bodies(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $collection = $workspace->collections()->create(['name' => 'Bodies', 'order' => 0]);

        Request::factory()->create([
            'collection_id' => $collection->id,
            'name' => 'Create User',
            'method' => HttpMethod::Post,
            'url' => 'https://api.example.com/users',
            'body_type' => BodyType::Json,
            'body' => ['json' => ['name' => 'Ada', 'age' => 30]],
        ]);

        $export = $this->actingAs($user)
            ->get(route('api.collections.download', $collection).'?format=openapi')
            ->assertOk()
            ->json();

        $this->assertSame(['url' => 'https://api.example.com'], $export['servers'][0]);

        $content = $export['paths']['/users']['post']['requestBody']['content']['application/json'];
        $this->assertSame(['name' => 'Ada', 'age' => 30], $content['example']);
        $this->assertSame('object', $content['schema']['type']);
        $this->assertSame('integer', $content['schema']['properties']['age']['type']);
    }

    public function test_a_non_member_cannot_export_a_collection_as_openapi(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $collection = $workspace->collections()->create(['name' => 'Private', 'order' => 0]);

        $this->actingAs($stranger)
            ->get(route('api.collections.download', $collection).'?format=openapi')
            ->assertForbidden();
    }
}
