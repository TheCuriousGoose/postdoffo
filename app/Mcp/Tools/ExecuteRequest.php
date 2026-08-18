<?php

namespace App\Mcp\Tools;

use App\Actions\ExecuteRequestAction;
use App\DTOs\ExecutedResponseData;
use App\Mcp\Presenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;

#[Title('Execute request')]
#[Description(
    'Send one saved request and return the response, along with the result of every assertion in '
    .'its test script. This makes a real HTTP call to a real third-party server from the '
    .'PostDoffo host, and is recorded in the workspace history — treat a POST/PUT/PATCH/DELETE '
    .'as something that changes the world, and confirm with the user before firing one you were '
    .'not explicitly asked to send. Long response bodies are truncated.'
)]
#[IsOpenWorld]
class ExecuteRequest extends BaseTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'request_id' => $schema->integer()->required(),
            'environment_id' => $schema->integer()->description('Run against this environment. Defaults to the workspace\'s active one.'),
        ];
    }

    public function handle(Request $request, ExecuteRequestAction $action): ResponseFactory
    {
        $validated = $request->validate([
            'request_id' => ['required', 'integer'],
            'environment_id' => ['sometimes', 'integer'],
        ]);

        // 'view', not 'edit': sending is something a viewer is allowed to do in
        // the app, and nothing here writes to the request itself.
        $apiRequest = $this->apiRequest((int) $validated['request_id'], 'view');

        $environment = isset($validated['environment_id'])
            ? $this->environmentIn($apiRequest->collection->workspace, (int) $validated['environment_id'])
            : null;

        $result = $action->handle($apiRequest, $this->user(), $environment);

        return $this->json([
            'request' => ['id' => $apiRequest->id, 'name' => $apiRequest->name],
            'response' => self::response($result),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function response(ExecutedResponseData $result): array
    {
        $tests = array_map(fn ($test) => $test->toArray(), $result->testResults);

        return [
            'status' => $result->status,
            'ok' => $result->ok(),
            'duration_ms' => $result->durationMs,
            'headers' => $result->headers,
            'body' => $result->body === null ? null : Presenter::truncate($result->body),
            // The transport-level failure (DNS, timeout, a blocked private host).
            // Distinct from a 4xx/5xx, which is a response the server did send.
            'error' => $result->error,
            'tests' => [
                'total' => count($tests),
                'passed' => count(array_filter($tests, fn ($test) => $test['passed'])),
                'failed' => count(array_filter($tests, fn ($test) => ! $test['passed'])),
                'results' => $tests,
            ],
        ];
    }
}
