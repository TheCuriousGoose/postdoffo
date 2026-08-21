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

#[Title('Rename workspace')]
#[Description('Rename a workspace. Requires the owner or a co-owner role.')]
#[IsIdempotent]
class RenameWorkspace extends BaseTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_id' => $schema->string()->required(),
            'name' => $schema->string()->max(255)->required(),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'workspace_id' => ['required', 'string', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $workspace = $this->workspace($validated['workspace_id'], 'update');
        $workspace->update(['name' => $validated['name']]);

        return $this->json([
            'workspace' => Presenter::workspace($workspace, $this->user()),
        ]);
    }
}
