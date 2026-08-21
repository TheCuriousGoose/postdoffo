<?php

namespace Tests\Feature;

use App\Actions\ExecuteRequestAction;
use App\Enums\BodyType;
use App\Enums\HttpMethod;
use App\Models\Collection;
use App\Models\Environment;
use App\Models\EnvironmentVariable;
use App\Models\Request as RequestModel;
use App\Models\RequestHistory;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExecuteRequestActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_executes_a_request_with_interpolated_variables_and_records_history(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $collection = Collection::factory()->create([
            'workspace_id' => $workspace->id,
            'variables' => ['base_url' => 'https://collection.example.com'],
        ]);

        $environment = Environment::factory()->create([
            'workspace_id' => $workspace->id,
            'is_active' => true,
        ]);
        EnvironmentVariable::factory()->create([
            'environment_id' => $environment->id,
            'key' => 'base_url',
            'value' => 'https://api.example.com',
        ]);

        $request = RequestModel::factory()->create([
            'collection_id' => $collection->id,
            'method' => HttpMethod::Get,
            'url' => '{{base_url}}/users/1',
            'headers' => [['key' => 'Accept', 'value' => 'application/json', 'enabled' => true]],
            'test_script' => 'pm.test("status is 200", pm.response.status == 200)'."\n"
                .'pm.test("has name", pm.response.json.name == "Ada")',
        ]);

        Http::fake([
            'api.example.com/*' => Http::response(['id' => 1, 'name' => 'Ada'], 200),
        ]);

        $result = app(ExecuteRequestAction::class)->handle($request, $user);

        Http::assertSent(function ($sentRequest) {
            return $sentRequest->url() === 'https://api.example.com/users/1'
                && $sentRequest->hasHeader('Accept', 'application/json');
        });

        $this->assertSame(200, $result->status);
        $this->assertTrue($result->ok());
        $this->assertCount(2, $result->testResults);
        $this->assertTrue($result->testResults[0]->passed);
        $this->assertTrue($result->testResults[1]->passed);

        $this->assertSame(1, RequestHistory::count());
        $history = RequestHistory::first();
        $this->assertSame($request->id, $history->request_id);
        $this->assertSame($workspace->id, $history->workspace_id);
        $this->assertSame(200, $history->status_code);
    }

    public function test_pre_request_script_can_inject_a_header_and_variable(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);

        $request = RequestModel::factory()->create([
            'collection_id' => $collection->id,
            'url' => 'https://api.example.com/ping',
            'pre_request_script' => 'pm.request.headers.set("X-Trace-Id", "trace-123")',
        ]);

        Http::fake(['api.example.com/*' => Http::response('pong', 200)]);

        app(ExecuteRequestAction::class)->handle($request, $user);

        Http::assertSent(fn ($sentRequest) => $sentRequest->hasHeader('X-Trace-Id', 'trace-123'));
    }

    public function test_environment_set_in_a_test_script_persists_to_the_environment(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);

        $environment = Environment::factory()->create([
            'workspace_id' => $workspace->id,
            'is_active' => true,
        ]);

        $request = RequestModel::factory()->create([
            'collection_id' => $collection->id,
            'url' => 'https://api.example.com/login',
            'test_script' => 'pm.environment.set("token", pm.response.json.access_token)',
        ]);

        Http::fake(['api.example.com/*' => Http::response(['access_token' => 'secret-token'], 200)]);

        $result = app(ExecuteRequestAction::class)->handle($request, $user, $environment);

        $this->assertSame('secret-token', $result->variables['token']);

        $saved = EnvironmentVariable::where('environment_id', $environment->id)->where('key', 'token')->first();
        $this->assertNotNull($saved);
        $this->assertSame('secret-token', $saved->value);
        $this->assertSame([
            ['id' => $saved->id, 'key' => 'token', 'value' => 'secret-token'],
        ], $result->environmentUpdates);
    }

    public function test_environment_set_updates_an_existing_variable_rather_than_duplicating_it(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);

        $environment = Environment::factory()->create([
            'workspace_id' => $workspace->id,
            'is_active' => true,
        ]);
        $existing = EnvironmentVariable::factory()->create([
            'environment_id' => $environment->id,
            'key' => 'token',
            'value' => 'stale-token',
        ]);

        $request = RequestModel::factory()->create([
            'collection_id' => $collection->id,
            'url' => 'https://api.example.com/login',
            'test_script' => 'pm.environment.set("token", pm.response.json.access_token)',
        ]);

        Http::fake(['api.example.com/*' => Http::response(['access_token' => 'rotated-token'], 200)]);

        app(ExecuteRequestAction::class)->handle($request, $user, $environment);

        $this->assertSame(1, EnvironmentVariable::where('environment_id', $environment->id)->where('key', 'token')->count());
        $this->assertSame($existing->id, $existing->fresh()->id);
        $this->assertSame('rotated-token', $existing->fresh()->value);
    }

    public function test_environment_set_in_a_pre_request_script_persists_to_the_environment(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);

        $environment = Environment::factory()->create([
            'workspace_id' => $workspace->id,
            'is_active' => true,
        ]);

        $request = RequestModel::factory()->create([
            'collection_id' => $collection->id,
            'url' => 'https://api.example.com/ping',
            'pre_request_script' => 'pm.environment.set("run_id", "abc123")',
        ]);

        Http::fake(['api.example.com/*' => Http::response('pong', 200)]);

        app(ExecuteRequestAction::class)->handle($request, $user, $environment);

        $this->assertSame('abc123', $environment->variables()->where('key', 'run_id')->first()->value);
    }

    public function test_variables_set_does_not_persist_to_the_environment(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);

        $environment = Environment::factory()->create([
            'workspace_id' => $workspace->id,
            'is_active' => true,
        ]);

        $request = RequestModel::factory()->create([
            'collection_id' => $collection->id,
            'url' => 'https://api.example.com/ping',
            'test_script' => 'pm.variables.set("scratch", "only-for-this-run")',
        ]);

        Http::fake(['api.example.com/*' => Http::response('pong', 200)]);

        $result = app(ExecuteRequestAction::class)->handle($request, $user, $environment);

        $this->assertSame('only-for-this-run', $result->variables['scratch']);
        $this->assertSame([], $result->environmentUpdates);
        $this->assertDatabaseMissing('environment_variables', ['key' => 'scratch']);
    }

    public function test_json_body_is_interpolated_and_sent(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $collection = Collection::factory()->create([
            'workspace_id' => $workspace->id,
            'variables' => ['name' => 'Grace'],
        ]);

        $request = RequestModel::factory()->create([
            'collection_id' => $collection->id,
            'method' => HttpMethod::Post,
            'url' => 'https://api.example.com/users',
            'body_type' => BodyType::Json,
            'body' => ['json' => ['name' => '{{name}}']],
        ]);

        Http::fake(['api.example.com/*' => Http::response(['created' => true], 201)]);

        $result = app(ExecuteRequestAction::class)->handle($request, $user);

        Http::assertSent(fn ($sentRequest) => $sentRequest['name'] === 'Grace');
        $this->assertSame(201, $result->status);
    }
}
