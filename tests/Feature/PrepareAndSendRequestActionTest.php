<?php

namespace Tests\Feature;

use App\Actions\PrepareRequestAction;
use App\Actions\RecordClientExecutedRequestAction;
use App\Actions\SendPreparedRequestAction;
use App\Models\Collection;
use App\Models\Request as RequestModel;
use App\Models\RequestHistory;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers the split prepare()/send()/record() pipeline used to send .test/.local
 * requests from the browser instead of proxying them through this server.
 */
class PrepareAndSendRequestActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_prepare_resolves_variables_and_pre_request_script_without_firing_the_request(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $collection = Collection::factory()->create([
            'workspace_id' => $workspace->id,
            'variables' => ['base_url' => 'http://myapp.test'],
        ]);

        $request = RequestModel::factory()->create([
            'collection_id' => $collection->id,
            'url' => '{{base_url}}/ping',
            'pre_request_script' => 'pm.request.headers.set("X-Trace-Id", "trace-123")',
        ]);

        Http::preventStrayRequests();

        $prepared = app(PrepareRequestAction::class)->handle($request);

        $this->assertSame('http://myapp.test/ping', $prepared->outgoing->url);
        $this->assertSame('trace-123', $prepared->outgoing->headers['X-Trace-Id']);
        $this->assertSame(0, RequestHistory::count());
    }

    public function test_send_prepared_request_fires_it_once_and_records_history_without_redoing_the_pre_request_script(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);

        $request = RequestModel::factory()->create([
            'collection_id' => $collection->id,
            'url' => 'https://api.example.com/ping',
            'test_script' => 'pm.test("status is 200", pm.response.status == 200)',
        ]);

        Http::fake(['api.example.com/*' => Http::response('pong', 200)]);

        $prepared = app(PrepareRequestAction::class)->handle($request);
        $result = app(SendPreparedRequestAction::class)->handle($request, $user, $prepared);

        Http::assertSentCount(1);
        $this->assertSame(200, $result->status);
        $this->assertTrue($result->testResults[0]->passed);

        $this->assertSame(1, RequestHistory::count());
        $this->assertSame($workspace->id, RequestHistory::first()->workspace_id);
    }

    public function test_record_client_executed_request_runs_the_test_script_against_the_browsers_result(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);

        $request = RequestModel::factory()->create([
            'collection_id' => $collection->id,
            'url' => 'http://myapp.local/ping',
            'test_script' => 'pm.test("status is 201", pm.response.status == 201)',
        ]);

        Http::preventStrayRequests();

        $prepared = app(PrepareRequestAction::class)->handle($request);

        $result = app(RecordClientExecutedRequestAction::class)->handle(
            $request,
            $user,
            $prepared->variables,
            201,
            ['Content-Type' => ['application/json']],
            '{"ok":true}',
            42,
            null,
        );

        $this->assertSame(201, $result->status);
        $this->assertSame(42, $result->durationMs);
        $this->assertTrue($result->testResults[0]->passed);

        $this->assertSame(1, RequestHistory::count());
        $history = RequestHistory::first();
        $this->assertSame(201, $history->status_code);
        $this->assertSame($workspace->id, $history->workspace_id);
    }
}
