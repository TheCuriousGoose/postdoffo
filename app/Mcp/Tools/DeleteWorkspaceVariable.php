<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Title('Delete workspace variable')]
#[Description('Remove one workspace-level variable. Environment variables of the same name are unaffected.')]
#[IsDestructive]
#[IsIdempotent]
class DeleteWorkspaceVariable extends BaseTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_id' => $schema->string()->required(),
            'key' => $schema->string()->required(),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'workspace_id' => ['required', 'string', 'uuid'],
            'key' => ['required', 'string'],
        ]);

        $workspace = $this->workspace($validated['workspace_id'], 'edit');

        $variable = $workspace->variables()->where('key', $validated['key'])->first();

        if ($variable === null) {
            throw ValidationException::withMessages([
                'key' => "Workspace {$workspace->id} has no workspace-level variable named \"{$validated['key']}\".",
            ]);
        }

        $variable->delete();

        return $this->json([
            'deleted' => true,
            'workspace_id' => $workspace->id,
            'key' => $validated['key'],
        ]);
    }
}
