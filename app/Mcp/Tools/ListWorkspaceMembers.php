<?php

namespace App\Mcp\Tools;

use App\Enums\WorkspaceRole;
use App\Models\WorkspaceMember;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Title('List workspace members')]
#[Description(
    'Who has access to a workspace and with what role. Useful for explaining to the user why a '
    .'write was refused. Inviting and removing people is deliberately not available over MCP — '
    .'those send mail to third parties and change who can see the work, so they stay with a '
    .'human, in the app.'
)]
#[IsReadOnly]
#[IsIdempotent]
class ListWorkspaceMembers extends BaseTool
{
    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'workspace_id' => $schema->string()->required(),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'workspace_id' => ['required', 'string', 'uuid'],
        ]);

        $workspace = $this->workspace($validated['workspace_id'], 'view');
        $workspace->load('owner');

        // The owner is not a membership row — it lives on workspaces.owner_id —
        // so it is prepended rather than queried alongside the rest.
        $members = [[
            'name' => $workspace->owner->name,
            'email' => $workspace->owner->email,
            'role' => WorkspaceRole::Owner->value,
        ]];

        $memberships = WorkspaceMember::query()
            ->where('workspace_id', $workspace->id)
            ->with('user')
            ->get();

        foreach ($memberships as $membership) {
            if ($membership->user === null) {
                continue;
            }

            $members[] = [
                'name' => $membership->user->name,
                'email' => $membership->user->email,
                'role' => $membership->role->value,
            ];
        }

        return $this->json([
            'workspace_id' => $workspace->id,
            'members' => $members,
        ]);
    }
}
