<?php

namespace App\Mcp\Tools;

use App\Mcp\Presenter;
use App\Models\Workspace;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Title('List workspaces')]
#[Description('List every workspace you can reach, with your role in each. Start here — every other tool needs an id from this listing.')]
#[IsReadOnly]
#[IsIdempotent]
class ListWorkspaces extends BaseTool
{
    public function handle(Request $request): ResponseFactory
    {
        $user = $this->user();

        $workspaces = Workspace::query()
            ->where('owner_id', $user->id)
            ->orWhereHas('members', fn ($query) => $query->where('user_id', $user->id))
            ->withCount('collections')
            ->orderBy('name')
            ->get();

        return $this->json([
            'workspaces' => $workspaces
                ->map(fn (Workspace $workspace) => Presenter::workspace($workspace, $user))
                ->all(),
        ]);
    }
}
