<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    /**
     * List every workspace the current user is a member of (owner or invited).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $workspaces = $user->ownedWorkspaces()
            ->orWhereHas('members', fn ($query) => $query->where('user_id', $user->id))
            ->withCount('collections')
            ->orderBy('name')
            ->get();

        $workspaces->each(function (Workspace $workspace) use ($user) {
            $workspace->role = $workspace->roleFor($user)?->value;
        });

        return Inertia::render('workspaces/Index', [
            'workspaces' => $workspaces,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $workspace = new Workspace(['name' => $data['name']]);
        $workspace->owner_id = $request->user()->id;
        $workspace->save();

        return to_route('workspaces.show', $workspace);
    }

    public function show(Request $request, Workspace $workspace): Response
    {
        $this->authorize('view', $workspace);

        $user = $request->user();

        if ($user->last_workspace_id !== $workspace->id) {
            $user->last_workspace_id = $workspace->id;
            $user->save();
        }

        $collections = $workspace->collections()->orderBy('order')->orderBy('name')->with('requests')->get();
        $environments = $workspace->environments()->with('variables')->orderBy('name')->get();
        $history = $workspace->requestHistory()->recent()->limit(50)->get();

        return Inertia::render('workspaces/Show', [
            'workspace' => $workspace,
            'collectionTree' => $this->buildTree($collections),
            'environments' => $environments,
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
    private function buildTree($collections, ?int $parentId = null): array
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
