<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Title('Delete environment')]
#[Description(
    'Delete an environment and every variable in it. Any request referencing one of those '
    .'variables will send the literal {{name}} instead once it is gone, so check what depends on '
    .'it first. Secret values cannot be recovered afterwards.'
)]
#[IsDestructive]
class DeleteEnvironment extends BaseTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'environment_id' => $schema->integer()->required(),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'environment_id' => ['required', 'integer'],
        ]);

        $environment = $this->environment((int) $validated['environment_id'], 'edit');

        $id = $environment->id;
        $name = $environment->name;
        $variableCount = $environment->variables()->count();

        $environment->delete();

        return $this->json([
            'deleted' => true,
            'environment' => ['id' => $id, 'name' => $name],
            'deleted_variables' => $variableCount,
        ]);
    }
}
