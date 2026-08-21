<?php

namespace App\Mcp\Tools;

use App\Enums\AuthType;
use App\Mcp\Presenter;
use App\Mcp\Schemas;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

#[Title('Create collection')]
#[Description(
    'Create a collection, or a folder inside one by passing parent_id — they are the same thing '
    .'at different depths. Headers and auth set here are inherited by every request underneath, '
    .'which is the right place for the ones that never vary: put the bearer token on the '
    .'collection once instead of on each request.'
)]
class CreateCollection extends BaseTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_id' => $schema->string()->required(),
            'name' => $schema->string()->max(255)->required(),
            'parent_id' => $schema->string()->description('Nest inside this collection, making it a folder. Omit for a top-level collection.'),
            'headers' => Schemas::keyValueList($schema, 'Headers sent with every request in this collection.'),
            'auth_type' => Schemas::authType($schema),
            'auth' => Schemas::auth($schema),
            'variables' => $schema->object()->description('Collection-scoped variables, as a flat {"key": "value"} map. Overridden by environment variables of the same name.'),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'workspace_id' => ['required', 'string', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'string', 'uuid'],
            'headers' => ['nullable', 'array'],
            'auth_type' => ['nullable', Rule::enum(AuthType::class)],
            'auth' => ['nullable', 'array'],
            'variables' => ['nullable', 'array'],
        ]);

        $workspace = $this->workspace($validated['workspace_id'], 'edit');

        $parentId = null;

        if (! empty($validated['parent_id'])) {
            // Scoped to this workspace rather than looked up globally: without it
            // a parent_id from elsewhere would graft a folder onto another
            // workspace's tree.
            $parentId = $this->collection($validated['parent_id'], 'edit')->id;
        }

        $collection = $workspace->collections()->create([
            'name' => $validated['name'],
            'parent_id' => $parentId,
            'headers' => $validated['headers'] ?? null,
            'auth_type' => $validated['auth_type'] ?? null,
            'auth' => $validated['auth'] ?? null,
            'variables' => $validated['variables'] ?? null,
            'order' => $workspace->collections()->where('parent_id', $parentId)->count(),
        ]);

        return $this->json([
            'collection' => Presenter::collection($collection),
        ]);
    }
}
