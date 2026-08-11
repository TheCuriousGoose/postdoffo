<?php

namespace App\Actions;

use App\Actions\Concerns\RecordsRequestHistory;
use App\DTOs\ExecutedResponseData;
use App\Models\Environment;
use App\Models\Request;
use App\Models\User;
use App\Services\RequestExecutorService;

/**
 * Executes a request and records the run in workspace history — the single
 * entry point the controller (or an import/replay job) calls to fire a request.
 */
class ExecuteRequestAction
{
    use RecordsRequestHistory;

    public function __construct(private readonly RequestExecutorService $executor) {}

    public function handle(Request $request, ?User $user = null, ?Environment $environment = null): ExecutedResponseData
    {
        $request->loadMissing('collection');

        $environment ??= Environment::forWorkspace($request->collection->workspace_id)->active()->first();

        $result = $this->executor->execute($request, $environment, $user);

        $this->recordHistory($request, $user, $result);

        return $result;
    }
}
