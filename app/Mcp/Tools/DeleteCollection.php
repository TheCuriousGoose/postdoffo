<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Title('Delete collection')]
#[Description(
    'Delete a collection or folder, along with every subfolder and request inside it. This is '
    .'not a move to a trash — nothing comes back. Check what is underneath with get_workspace '
    .'first, and say what will be lost before you call it.'
)]
#[IsDestructive]
class DeleteCollection extends BaseTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'collection_id' => $schema->integer()->required(),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'collection_id' => ['required', 'integer'],
        ]);

        $collection = $this->collection((int) $validated['collection_id'], 'edit');

        $deletedRequests = $collection->requests()->count();
        $deletedChildren = $collection->children()->count();
        $id = $collection->id;
        $name = $collection->name;

        $collection->delete();

        return $this->json([
            'deleted' => true,
            'collection' => ['id' => $id, 'name' => $name],
            'deleted_direct_requests' => $deletedRequests,
            'deleted_direct_subfolders' => $deletedChildren,
        ]);
    }
}
