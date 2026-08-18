<?php

namespace App\Mcp\Tools;

use App\Actions\Concerns\RecordsRequestHistory;
use App\Mcp\Presenter;
use App\Models\Collection;
use App\Models\Environment;
use App\Models\Request as ApiRequest;
use App\Services\RequestExecutorService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Title('Run collection')]
#[Description(
    'Fire every request in a collection in order and return one pass/fail report. This is the '
    .'test-suite runner: use it after building or changing a collection to see which requests '
    .'still hold up. Requests run in their saved order and share variables as they go, so a '
    .'login at the top that calls pm.environment.set("token", ...) hands that token to '
    .'everything after it. Every request is a real outbound call — say what a run will hit '
    .'before starting one against production.'
)]
#[IsOpenWorld]
class RunCollection extends BaseTool
{
    use RecordsRequestHistory;

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'collection_id' => $schema->integer()->required(),
            'environment_id' => $schema->integer()->description('Run against this environment. Defaults to the workspace\'s active one.'),
            'include_subfolders' => $schema->boolean()->description('Also run requests in nested folders, depth first. Defaults to true.'),
            'stop_on_failure' => $schema->boolean()->description('Stop at the first request whose tests fail or that errors. Defaults to false — a full report is usually more useful.'),
            'include_bodies' => $schema->boolean()->description('Include each response body in the report. Defaults to false: across a run of any size the bodies dwarf the results.'),
        ];
    }

    public function handle(Request $request, RequestExecutorService $executor): ResponseFactory
    {
        $validated = $request->validate([
            'collection_id' => ['required', 'integer'],
            'environment_id' => ['sometimes', 'integer'],
            'include_subfolders' => ['sometimes', 'boolean'],
            'stop_on_failure' => ['sometimes', 'boolean'],
            'include_bodies' => ['sometimes', 'boolean'],
        ]);

        $collection = $this->collection((int) $validated['collection_id'], 'view');
        $stopOnFailure = $validated['stop_on_failure'] ?? false;
        $includeBodies = $validated['include_bodies'] ?? false;

        $environment = isset($validated['environment_id'])
            ? $this->environmentIn($collection->workspace, (int) $validated['environment_id'])
            : Environment::forWorkspace($collection->workspace_id)->active()->first();

        $limit = (int) config('mcp.max_requests_per_run');
        $queue = $this->requestsToRun($collection, $validated['include_subfolders'] ?? true);
        $skipped = max(0, count($queue) - $limit);
        $queue = array_slice($queue, 0, $limit);

        $user = $this->user();

        /**
         * Variables set by one request's scripts, carried into the next as the
         * highest-precedence layer. This is what makes a run a run rather than a
         * loop of unrelated sends — without it, the token a login stores would be
         * gone by the time the request needing it is prepared.
         *
         * @var array<string, string>
         */
        $carried = [];

        $results = [];
        $stoppedEarly = false;

        foreach ($queue as $apiRequest) {
            $prepared = $executor->prepare($apiRequest, $environment, $carried);
            $result = $executor->sendAndFinalize($apiRequest, $prepared, $user);

            // Recorded the same way a send from the app is, so a run shows up in
            // the workspace's history instead of happening invisibly.
            $this->recordHistory($apiRequest, $user, $result);

            $carried = [...$carried, ...$result->variables];

            $tests = array_map(fn ($test) => $test->toArray(), $result->testResults);
            $failed = count(array_filter($tests, fn ($test) => ! $test['passed']));

            $entry = [
                'request_id' => $apiRequest->id,
                'name' => $apiRequest->name,
                'method' => $apiRequest->method->value,
                'status' => $result->status,
                'ok' => $result->ok(),
                'duration_ms' => $result->durationMs,
                'error' => $result->error,
                'tests_passed' => count($tests) - $failed,
                'tests_failed' => $failed,
                // Only the failures are spelled out: the name of a passing
                // assertion tells you nothing you can act on, and a fifty-request
                // run would otherwise come back as mostly noise.
                'failures' => array_values(array_filter($tests, fn ($test) => ! $test['passed'])),
            ];

            if ($includeBodies && $result->body !== null) {
                $entry['body'] = Presenter::truncate($result->body);
            }

            $results[] = $entry;

            if ($stopOnFailure && ($failed > 0 || $result->error !== null)) {
                $stoppedEarly = true;
                break;
            }
        }

        return $this->json([
            'collection' => ['id' => $collection->id, 'name' => $collection->name],
            'environment' => $environment === null ? null : ['id' => $environment->id, 'name' => $environment->name],
            'summary' => [
                'requests_run' => count($results),
                'requests_ok' => count(array_filter($results, fn ($entry) => $entry['ok'])),
                'requests_failed' => count(array_filter($results, fn ($entry) => ! $entry['ok'] || $entry['tests_failed'] > 0)),
                'tests_passed' => array_sum(array_column($results, 'tests_passed')),
                'tests_failed' => array_sum(array_column($results, 'tests_failed')),
                'stopped_on_failure' => $stoppedEarly,
                'skipped_over_limit' => $skipped,
            ],
            'results' => $results,
        ]);
    }

    /**
     * Flattens the subtree into the order someone reading the sidebar would take
     * it in: a folder's own requests first, then each child folder's, depth
     * first. That order is the contract the variable chaining above relies on.
     *
     * @return array<int, ApiRequest>
     */
    private function requestsToRun(Collection $collection, bool $includeSubfolders): array
    {
        $requests = $collection->requests()->orderBy('order')->orderBy('name')->get()->all();

        if (! $includeSubfolders) {
            return $requests;
        }

        foreach ($collection->children()->get() as $child) {
            $requests = [...$requests, ...$this->requestsToRun($child, true)];
        }

        return $requests;
    }
}
