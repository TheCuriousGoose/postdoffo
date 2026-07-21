<?php

namespace App\Actions\Concerns;

use App\DTOs\ExecutedResponseData;
use App\Models\Request;
use App\Models\RequestHistory;
use App\Models\User;

trait RecordsRequestHistory
{
    private function recordHistory(Request $request, ?User $user, ExecutedResponseData $result): void
    {
        RequestHistory::create([
            'request_id' => $request->id,
            'workspace_id' => $request->collection->workspace_id,
            'user_id' => $user?->id,
            'method' => $request->method->value,
            'url' => $request->url,
            'status_code' => $result->status,
            'duration_ms' => $result->durationMs,
            'response_snapshot' => $result->toArray(),
            'executed_at' => now(),
        ]);
    }
}
