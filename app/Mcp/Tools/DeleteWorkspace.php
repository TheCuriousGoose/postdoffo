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

#[Title('Delete workspace')]
#[Description(
    'Permanently delete a workspace and everything in it — collections, requests, environments '
    .'and history — for every member, not just you. Owner only, and it cannot be undone. '
    .'Confirm with the user in their own words before calling this.'
)]
#[IsDestructive]
class DeleteWorkspace extends BaseTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_id' => $schema->string()->required(),
            'confirm_name' => $schema->string()
                ->description('The exact name of the workspace being deleted. A deliberate speed bump on an irreversible, shared loss.')
                ->required(),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'workspace_id' => ['required', 'string', 'uuid'],
            'confirm_name' => ['required', 'string'],
        ]);

        $workspace = $this->workspace($validated['workspace_id'], 'delete');

        if ($validated['confirm_name'] !== $workspace->name) {
            throw ValidationException::withMessages([
                'confirm_name' => "confirm_name does not match. Workspace {$workspace->id} is named \"{$workspace->name}\". Nothing was deleted.",
            ]);
        }

        $id = $workspace->id;
        $name = $workspace->name;

        $workspace->delete();

        return $this->json([
            'deleted' => true,
            'workspace' => ['id' => $id, 'name' => $name],
        ]);
    }
}
