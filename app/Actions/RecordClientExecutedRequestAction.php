<?php

namespace App\Actions;

use App\Actions\Concerns\RecordsRequestHistory;
use App\DTOs\ExecutedResponseData;
use App\Models\Request;
use App\Models\User;
use App\Services\RequestExecutorService;

/**
 * The counterpart to PrepareRequestAction: takes the outcome of an HTTP call the
 * browser made on our behalf, runs the test script against it, and records history —
 * the same bookkeeping ExecuteRequestAction does for server-fired requests.
 */
class RecordClientExecutedRequestAction
{
    use RecordsRequestHistory;

    public function __construct(private readonly RequestExecutorService $executor) {}

    /**
     * @param  array<string, string>  $variables
     * @param  array<string, array<int, string>|string>  $headers
     */
    public function handle(
        Request $request,
        ?User $user,
        array $variables,
        ?int $status,
        array $headers,
        ?string $body,
        int $durationMs,
        ?string $error,
    ): ExecutedResponseData {
        $request->loadMissing('collection');

        $result = $this->executor->finalize($request, $variables, $status, $headers, $body, $durationMs, $error);

        $this->recordHistory($request, $user, $result);

        return $result;
    }
}
