<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Team;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    /**
     * List every workspace the current user can reach: owned directly,
     * individually invited into, or granted through a team they belong to.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $teamIds = $user->ownedTeams()->pluck('teams.id')
            ->merge($user->teams()->pluck('teams.id'))
            ->unique();

        $workspaces = Workspace::query()
            ->where(function ($query) use ($user, $teamIds) {
                $query->where('owner_id', $user->id)
                    ->orWhereHas('members', fn ($q) => $q->where('user_id', $user->id))
                    ->orWhereIn('team_id', $teamIds);
            })
            ->with('team:id,name')
            ->withCount('collections')
            ->orderBy('name')
            ->get();

        $workspaces->each(function (Workspace $workspace) use ($user) {
            $workspace->role = $workspace->roleFor($user)?->value;
        });

        // For the "move to team" picker: every team this user could plausibly
        // move a workspace they own into (see WorkspaceController::updateTeam()).
        $teams = Team::query()
            ->where('owner_id', $user->id)
            ->orWhereIn('id', $teamIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('workspaces/Index', [
            'workspaces' => $workspaces,
            'teams' => $teams,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'team_id' => ['nullable', 'string', 'uuid', 'exists:teams,id'],
        ]);

        $workspace = new Workspace(['name' => $data['name']]);
        $workspace->owner_id = $request->user()->id;

        if (! empty($data['team_id'])) {
            $team = Team::where('id', $data['team_id'])->firstOrFail();
            $this->authorize('manageWorkspaces', $team);
            $workspace->team_id = $team->id;
        }

        $workspace->save();

        return to_route('workspaces.show', $workspace);
    }

    /**
     * Move a workspace into a team, or back out to standalone. Left to the
     * workspace's real owner to decide either direction — attaching hands
     * every team member the access mapped in TeamRole::asWorkspaceRole(), so
     * it isn't something a co-owner or a team admin can do unilaterally on a
     * workspace they don't themselves own.
     */
    public function updateTeam(Request $request, Workspace $workspace): RedirectResponse
    {
        abort_unless($workspace->owner_id === $request->user()->id, 403, 'Only the workspace owner can move it between teams.');

        $data = $request->validate([
            'team_id' => ['nullable', 'string', 'uuid', 'exists:teams,id'],
        ]);

        if (! empty($data['team_id'])) {
            $team = Team::where('id', $data['team_id'])->firstOrFail();
            abort_unless($team->roleFor($request->user()) !== null, 403, 'You must be a member of the team to move a workspace into it.');
            $workspace->team_id = $team->id;
        } else {
            $workspace->team_id = null;
        }

        $workspace->save();

        return back();
    }

    public function show(Request $request, Workspace $workspace): Response
    {
        $this->authorize('view', $workspace);

        $user = $request->user();

        if ($user->last_workspace_id !== $workspace->id) {
            $user->last_workspace_id = $workspace->id;
            $user->save();
        }

        // Only the fields the sidebar row actually renders — the full request
        // (body, headers, scripts, ...) is fetched on demand when a tab opens
        // (RequestController::show). With thousands of requests in a workspace,
        // eagerly shipping every field of every request bloats the initial
        // Inertia payload by megabytes for no benefit.
        $collections = $workspace->collections()->orderBy('order')->orderBy('name')
            ->with(['requests:id,collection_id,name,method,order'])
            ->get();
        $environments = $workspace->environments()->with('variables')->orderBy('name')->get();
        $workspaceVariables = $workspace->variables()->orderBy('key')->get();

        $history = $workspace->requestHistory()
            ->recent()
            ->limit(50)
            ->get(['id', 'request_id', 'workspace_id', 'user_id', 'method', 'url', 'status_code', 'duration_ms', 'executed_at']);

        return Inertia::render('workspaces/Show', [
            'workspace' => $workspace,
            'collectionTree' => $this->buildTree($collections),
            'environments' => $environments,
            'workspaceVariables' => $workspaceVariables,
            'history' => $history,
            'role' => $workspace->roleFor($request->user())?->value,
        ]);
    }

    public function update(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('update', $workspace);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $workspace->update($data);

        return back();
    }

    public function destroy(Workspace $workspace): RedirectResponse
    {
        $this->authorize('delete', $workspace);

        $workspace->delete();

        return to_route('workspaces.index');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Collection>  $collections
     * @return array<int, array<string, mixed>>
     */
    private function buildTree($collections, ?string $parentId = null): array
    {
        return $collections
            ->where('parent_id', $parentId)
            ->map(fn (Collection $collection) => [
                'id' => $collection->id,
                'name' => $collection->name,
                'parent_id' => $collection->parent_id,
                'order' => $collection->order,
                'variables' => $collection->variables,
                'headers' => $collection->headers,
                'auth_type' => $collection->auth_type,
                'auth' => $collection->auth,
                'requests' => $collection->requests->values(),
                'children' => $this->buildTree($collections, $collection->id),
            ])
            ->values()
            ->all();
    }
}
