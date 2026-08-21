<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Title('Delete request')]
#[Description('Permanently delete a request, along with its scripts, uploaded form-data files and history. Cannot be undone.')]
#[IsDestructive]
class DeleteRequest extends BaseTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'request_id' => $schema->string()->required(),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'request_id' => ['required', 'string', 'uuid'],
        ]);

        $apiRequest = $this->apiRequest($validated['request_id'], 'edit');

        $id = $apiRequest->id;
        $name = $apiRequest->name;

        $apiRequest->delete();

        return $this->json([
            'deleted' => true,
            'request' => ['id' => $id, 'name' => $name],
        ]);
    }
}
