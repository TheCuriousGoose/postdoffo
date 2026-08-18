<?php

namespace App\Mcp\Tools;

use App\Actions\ExportCollectionAction;
use App\Actions\ExportOpenApiAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Title('Export collection')]
#[Description(
    'Export a collection as a Postman v2.1 collection or an OpenAPI 3.0 document. Use it to hand '
    .'the collection to another tool, or to generate API documentation from what has been built. '
    .'Large collections produce large documents — export a subfolder if you only need part.'
)]
#[IsReadOnly]
#[IsIdempotent]
class ExportCollection extends BaseTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'collection_id' => $schema->integer()->required(),
            'format' => $schema->string()->enum(['postman', 'openapi'])->description('Defaults to "postman".'),
        ];
    }

    public function handle(Request $request, ExportCollectionAction $postman, ExportOpenApiAction $openApi): ResponseFactory
    {
        $validated = $request->validate([
            'collection_id' => ['required', 'integer'],
            'format' => ['sometimes', 'in:postman,openapi'],
        ]);

        $collection = $this->collection((int) $validated['collection_id'], 'view');
        $format = $validated['format'] ?? 'postman';

        return $this->json([
            'format' => $format,
            'collection_id' => $collection->id,
            'document' => $format === 'openapi'
                ? $openApi->handle($collection)
                : $postman->handle($collection),
        ]);
    }
}
