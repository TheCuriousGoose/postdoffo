<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    /**
     * List every team the current user is a member of (owner or invited).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $teams = $user->ownedTeams()
            ->orWhereHas('members', fn ($query) => $query->where('user_id', $user->id))
            ->withCount('workspaces')
            ->orderBy('name')
            ->get();

        $teams->each(function (Team $team) use ($user) {
            $team->role = $team->roleFor($user)?->value;
        });

        return Inertia::render('teams/Index', [
            'teams' => $teams,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $team = new Team(['name' => $data['name']]);
        $team->owner_id = $request->user()->id;
        $team->save();

        return to_route('teams.show', $team);
    }

    public function show(Request $request, Team $team): Response
    {
        $this->authorize('view', $team);

        $workspaces = $team->workspaces()->withCount('collections')->orderBy('name')->get();

        return Inertia::render('teams/Show', [
            'team' => $team,
            'workspaces' => $workspaces,
            'role' => $team->roleFor($request->user())?->value,
        ]);
    }

    public function update(Request $request, Team $team): RedirectResponse
    {
        $this->authorize('update', $team);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $team->update($data);

        return back();
    }

    public function destroy(Team $team): RedirectResponse
    {
        $this->authorize('delete', $team);

        $team->delete();

        return to_route('teams.index');
    }
}
