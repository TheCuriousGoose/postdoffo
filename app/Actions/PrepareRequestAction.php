<?php

namespace App\Actions;

use App\DTOs\PreparedRequestData;
use App\Models\Environment;
use App\Models\Request;
use App\Services\RequestExecutorService;

/**
 * Resolves variables, runs the pre-request script, and builds the outgoing
 * request — without firing it. Used when the browser (not this server) is
 * going to make the actual HTTP call, e.g. for hosts like .test/.local that
 * only resolve on the developer's own machine.
 */
class PrepareRequestAction
{
    public function __construct(private readonly RequestExecutorService $executor) {}

    public function handle(Request $request, ?Environment $environment = null): PreparedRequestData
    {
        $request->loadMissing('collection.workspace');

        $environment ??= Environment::forWorkspace($request->collection->workspace_id)->active()->first();

        return $this->executor->prepare($request, $environment);
    }
}
