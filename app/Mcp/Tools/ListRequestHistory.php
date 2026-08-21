<?php

namespace App\Mcp\Tools;

use App\Mcp\Presenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Title('List request history')]
#[Description(
    'Recent request runs in a workspace, newest first — every send by anyone in it, from the app '
    .'or from here. Use it to see whether an endpoint was already failing before your change, or '
    .'what a request returned last time. Bodies are not included; fetch one with '
    .'get_request_history_entry.'
)]
#[IsReadOnly]
#[IsIdempotent]
class ListRequestHistory extends BaseTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_id' => $schema->string()->required(),
            'request_id' => $schema->string()->description('Only runs of this one request.'),
            'limit' => $schema->integer()->min(1)->max(100)->description('Defaults to 25.'),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'workspace_id' => ['required', 'string', 'uuid'],
            'request_id' => ['sometimes', 'string', 'uuid'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $workspace = $this->workspace($validated['workspace_id'], 'view');

        $entries = $workspace->requestHistory()
            ->when(
                isset($validated['request_id']),
                fn ($query) => $query->where('request_id', $validated['request_id']),
            )
            ->recent()
            ->limit((int) ($validated['limit'] ?? 25))
            ->get();

        return $this->json([
            'workspace_id' => $workspace->id,
            'history' => $entries
                ->map(fn ($entry) => Presenter::historyEntry($entry))
                ->all(),
        ]);
    }
}
