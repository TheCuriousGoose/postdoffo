<?php

namespace Tests\Feature;

use App\Enums\AuthType;
use App\Enums\BodyType;
use App\Enums\HttpMethod;
use App\Models\Collection;
use App\Models\Environment;
use App\Models\EnvironmentVariable;
use App\Models\Request as RequestModel;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurlInteropTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_request_can_be_created_by_pasting_curl(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);

        $this->actingAs($user)
            ->postJson(route('api.requests.from-curl', $collection), [
                'command' => 'curl -X POST https://api.example.com/users -H "Content-Type: application/json" -d \'{"name":"Ada"}\'',
            ])
            ->assertCreated()
            ->assertJsonPath('method', 'POST')
            ->assertJsonPath('url', 'https://api.example.com/users');

        $created = RequestModel::firstOrFail();

        $this->assertSame(BodyType::Json, $created->body_type);
        $this->assertSame(['name' => 'Ada'], $created->body['json']);
        $this->assertSame($collection->id, $created->collection_id);
    }

    public function test_pasting_something_that_is_not_curl_is_a_validation_error(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);

        $this->actingAs($user)
            ->postJson(route('api.requests.from-curl', $collection), ['command' => 'rm -rf /'])
            ->assertJsonValidationErrors('command');

        $this->assertSame(0, RequestModel::count());
    }

    public function test_a_viewer_cannot_create_a_request_from_curl(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);

        $this->actingAs($stranger)
            ->postJson(route('api.requests.from-curl', $collection), [
                'command' => 'curl https://api.example.com/users',
            ])
            ->assertForbidden();
    }

    public function test_copying_as_curl_resolves_variables_and_auth(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $collection = Collection::factory()->create([
            'workspace_id' => $workspace->id,
            'headers' => [['key' => 'Accept', 'value' => 'application/json', 'enabled' => true]],
        ]);

        $environment = Environment::factory()->create(['workspace_id' => $workspace->id, 'is_active' => true]);
        EnvironmentVariable::factory()->create([
            'environment_id' => $environment->id,
            'key' => 'base_url',
            'value' => 'https://api.example.com',
        ]);

        $request = RequestModel::factory()->create([
            'collection_id' => $collection->id,
            'method' => HttpMethod::Post,
            'url' => '{{base_url}}/users',
            'auth_type' => AuthType::Bearer,
            'auth' => ['token' => 'secret-token'],
            'body_type' => BodyType::Json,
            'body' => ['json' => ['name' => 'Ada']],
        ]);

        $command = $this->actingAs($user)
            ->getJson(route('api.requests.curl', $request))
            ->assertOk()
            ->json('command');

        $this->assertStringContainsString("'https://api.example.com/users'", $command);
        $this->assertStringContainsString('-X POST', $command);
        // The point of resolving: no {{base_url}} and a real Authorization value.
        $this->assertStringNotContainsString('{{base_url}}', $command);
        $this->assertStringContainsString("-H 'Authorization: Bearer secret-token'", $command);
        $this->assertStringContainsString("-H 'Accept: application/json'", $command);
        $this->assertStringContainsString('--data-raw \'{"name":"Ada"}\'', $command);
    }

    public function test_a_generated_command_can_be_pasted_back_in(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);

        $request = RequestModel::factory()->create([
            'collection_id' => $collection->id,
            'method' => HttpMethod::Put,
            'url' => 'https://api.example.com/users/1',
            'headers' => [['key' => 'X-Trace', 'value' => "it's quoted", 'enabled' => true]],
            'body_type' => BodyType::Json,
            'body' => ['json' => ['name' => "O'Brien"]],
        ]);

        $command = $this->actingAs($user)
            ->getJson(route('api.requests.curl', $request))
            ->json('command');

        // Round trip: the snippet has to survive being read back, apostrophes
        // and all, or "copy as curl" and "paste curl" disagree with each other.
        $this->actingAs($user)
            ->postJson(route('api.requests.from-curl', $collection), ['command' => $command])
            ->assertCreated();

        $reimported = RequestModel::where('id', '!=', $request->id)->firstOrFail();

        $this->assertSame(HttpMethod::Put, $reimported->method);
        $this->assertSame('https://api.example.com/users/1', $reimported->url);
        $this->assertSame(['name' => "O'Brien"], $reimported->body['json']);
        $this->assertContains(
            ['key' => 'X-Trace', 'value' => "it's quoted", 'enabled' => true],
            $reimported->headers,
        );
    }

    public function test_a_stranger_cannot_copy_a_request_as_curl(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);
        $request = RequestModel::factory()->create(['collection_id' => $collection->id]);

        $this->actingAs($stranger)
            ->getJson(route('api.requests.curl', $request))
            ->assertForbidden();
    }
}
