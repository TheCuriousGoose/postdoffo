<?php

namespace App\Mcp\Tools;

use App\Actions\ImportCollectionAction;
use App\Mcp\Presenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Import Postman collection')]
#[Description(
    'Import a Postman v2.x collection export into a workspace, keeping its folders, requests, '
    .'headers, auth and variables. Far better than recreating an export request by request: pass '
    .'the file contents as-is. Accepts the JSON as either an object or a raw string.'
)]
class ImportPostmanCollection extends BaseTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_id' => $schema->string()->required(),
            'collection' => $schema->object()->description('The parsed Postman export. Must contain info.name and item.'),
            'collection_json' => $schema->string()->description('The Postman export as a raw JSON string, if you have it as text. Use this or "collection", not both.'),
        ];
    }

    public function handle(Request $request, ImportCollectionAction $action): ResponseFactory
    {
        $validated = $request->validate([
            'workspace_id' => ['required', 'string', 'uuid'],
            'collection' => ['nullable', 'array'],
            'collection_json' => ['nullable', 'string'],
        ]);

        $workspace = $this->workspace($validated['workspace_id'], 'edit');

        $payload = $validated['collection'] ?? null;

        if ($payload === null && filled($validated['collection_json'] ?? null)) {
            $decoded = json_decode((string) $validated['collection_json'], true);

            if (! is_array($decoded)) {
                throw ValidationException::withMessages([
                    'collection_json' => 'collection_json is not valid JSON: '.json_last_error_msg(),
                ]);
            }

            $payload = $decoded;
        }

        if ($payload === null) {
            throw ValidationException::withMessages([
                'collection' => 'Pass the export as either "collection" (an object) or "collection_json" (a string).',
            ]);
        }

        if (! is_array($payload['info'] ?? null) || ! is_string($payload['info']['name'] ?? null)) {
            throw ValidationException::withMessages([
                'collection' => 'This is not a Postman collection export — info.name is missing. '
                    .'Environment exports and multi-collection bundles are imported through the app, not here.',
            ]);
        }

        $collection = $action->handle($workspace, $payload);
        $collection->load('requests', 'children');

        return $this->json([
            'collection' => Presenter::collection($collection),
            'imported_requests' => $collection->requests->count(),
            'imported_subfolders' => $collection->children->count(),
        ]);
    }
}
