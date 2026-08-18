<?php

namespace App\Mcp\Tools;

use App\Mcp\Presenter;
use App\Models\RequestHistory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Title('Get request history entry')]
#[Description(
    'One recorded run in full, including the response body and the test results as they stood at '
    .'the time. Lets you diagnose a past failure without firing the request again — which matters '
    .'when re-running it would repeat a side effect.'
)]
#[IsReadOnly]
#[IsIdempotent]
class GetRequestHistoryEntry extends BaseTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'history_id' => $schema->integer()->description('From list_request_history.')->required(),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'history_id' => ['required', 'integer'],
        ]);

        $entry = RequestHistory::find((int) $validated['history_id']);

        if ($entry === null) {
            throw ValidationException::withMessages([
                'history_id' => "No history entry with id {$validated['history_id']} exists.",
            ]);
        }

        // Authorized through the workspace that owns the entry, so an id from
        // another workspace is refused rather than answered.
        $this->workspace($entry->workspace_id, 'view');

        return $this->json([
            'entry' => Presenter::historyEntry($entry, withSnapshot: true),
        ]);
    }
}
