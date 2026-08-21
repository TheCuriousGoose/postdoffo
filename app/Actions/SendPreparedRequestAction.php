<?php

namespace App\Actions;

use App\Actions\Concerns\RecordsRequestHistory;
use App\DTOs\ExecutedResponseData;
use App\DTOs\PreparedRequestData;
use App\Models\Environment;
use App\Models\Request;
use App\Models\User;
use App\Services\RequestExecutorService;

/**
 * Fires an already-prepared request from this server and records history — used when
 * a request was prepared to check whether it targets a local-only host (.test/.local)
 * and turned out not to, so it falls back to the normal server-proxied path instead
 * of re-resolving variables and re-running the pre-request script from scratch.
 */
class SendPreparedRequestAction
{
    use RecordsRequestHistory;

    public function __construct(private readonly RequestExecutorService $executor) {}

    /**
     * `$environment` should be the same one `PrepareRequestAction` resolved this
     * request against — it's where a test-script pm.environment.set() call lands.
     */
    public function handle(Request $request, ?User $user, PreparedRequestData $prepared, ?Environment $environment = null): ExecutedResponseData
    {
        $request->loadMissing('collection');

        $environment ??= Environment::forWorkspace($request->collection->workspace_id)->active()->first();

        $result = $this->executor->sendAndFinalize($request, $prepared, $user, $environment);

        $this->recordHistory($request, $user, $result);

        return $result;
    }
}
