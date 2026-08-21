<?php

namespace App\Mcp\Tools;

use App\Enums\AuthType;
use App\Mcp\Presenter;
use App\Mcp\Schemas;
use App\Models\Collection;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Title('Update collection')]
#[Description(
    'Change a collection: rename it, move it under a different parent, or set the headers, auth '
    .'and variables its requests inherit. Only the arguments you pass are touched — omitting one '
    .'leaves it as it was, so you do not need to resend the whole collection to change its name.'
)]
#[IsIdempotent]
class UpdateCollection extends BaseTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'collection_id' => $schema->string()->required(),
            'name' => $schema->string()->max(255),
            'parent_id' => $schema->string()->nullable()->description('Move under this collection. Pass null to move it back to the top level.'),
            'headers' => Schemas::keyValueList($schema, 'Replaces the inherited header list entirely.'),
            'auth_type' => Schemas::authType($schema),
            'auth' => Schemas::auth($schema),
            'variables' => $schema->object()->description('Replaces the collection variable map entirely.'),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'collection_id' => ['required', 'string', 'uuid'],
            'name' => ['sometimes', 'string', 'max:255'],
            'parent_id' => ['sometimes', 'nullable', 'string', 'uuid'],
            'headers' => ['sometimes', 'nullable', 'array'],
            'auth_type' => ['sometimes', 'nullable', Rule::enum(AuthType::class)],
            'auth' => ['sometimes', 'nullable', 'array'],
            'variables' => ['sometimes', 'nullable', 'array'],
        ]);

        $collection = $this->collection($validated['collection_id'], 'edit');

        unset($validated['collection_id']);

        if (array_key_exists('parent_id', $validated) && $validated['parent_id'] !== null) {
            $parent = $this->collection($validated['parent_id'], 'edit');

            if ($parent->workspace_id !== $collection->workspace_id) {
                throw ValidationException::withMessages([
                    'parent_id' => 'A collection can only be moved within its own workspace.',
                ]);
            }

            $this->assertNotDescendant($collection, $parent);

            $validated['parent_id'] = $parent->id;
        }

        $collection->update($validated);

        return $this->json([
            'collection' => Presenter::collection($collection->fresh()),
        ]);
    }

    /**
     * Moving a folder into its own subtree would cut the whole branch loose from
     * the root, leaving it unreachable in the sidebar.
     */
    private function assertNotDescendant(Collection $collection, Collection $candidateParent): void
    {
        $current = $candidateParent;

        while ($current !== null) {
            if ($current->id === $collection->id) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Cannot move a collection into itself or one of its own subfolders.',
                ]);
            }

            $current = $current->parent_id !== null ? Collection::find($current->parent_id) : null;
        }
    }
}
