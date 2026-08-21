<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Send the user straight into a workspace: their last-visited one if
     * they still have access, otherwise their most recently updated one,
     * otherwise the (empty) workspace list.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->last_workspace_id) {
            $workspace = Workspace::find($user->last_workspace_id);

            if ($workspace && $workspace->roleFor($user)) {
                return to_route('workspaces.show', $workspace);
            }
        }

        $teamIds = $user->ownedTeams()->pluck('teams.id')
            ->merge($user->teams()->pluck('teams.id'))
            ->unique();

        $workspace = Workspace::query()
            ->where('owner_id', $user->id)
            ->orWhereHas('members', fn ($query) => $query->where('user_id', $user->id))
            ->orWhereIn('team_id', $teamIds)
            ->orderByDesc('updated_at')
            ->first();

        if (! $workspace) {
            return to_route('workspaces.index');
        }

        $user->last_workspace_id = $workspace->id;
        $user->save();

        return to_route('workspaces.show', $workspace);
    }
}
