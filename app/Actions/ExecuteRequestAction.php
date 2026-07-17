<?php

namespace App\Actions;

use App\DTOs\ExecutedResponseData;
use App\Models\Environment;
use App\Models\Request;
use App\Models\RequestHistory;
use App\Models\User;
use App\Services\RequestExecutorService;

/**
 * Executes a request and records the run in workspace history — the single
 * entry point the controller (or an import/replay job) calls to fire a request.
 */
class ExecuteRequestAction
{
    public function __construct(private readonly RequestExecutorService $executor) {}

    public function handle(Request $request, ?User $user = null, ?Environment $environment = null): ExecutedResponseData
    {
        $request->loadMissing('collection.workspace');
        $workspace = $request->collection->workspace;

        $environment ??= Environment::forWorkspace($workspace->id)->active()->first();

        $result = $this->executor->execute($request, $environment);

        RequestHistory::create([
            'request_id' => $request->id,
            'workspace_id' => $workspace->id,
            'user_id' => $user?->id,
            'method' => $request->method->value,
            'url' => $request->url,
            'status_code' => $result->status,
            'duration_ms' => $result->durationMs,
            'response_snapshot' => $result->toArray(),
            'executed_at' => now(),
        ]);

        return $result;
    }
}
