<?php

namespace App\Http\Controllers;

use App\Models\TeamInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TeamInvitationController extends Controller
{
    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = TeamInvitation::where('token', $token)->firstOrFail();
        $user = $request->user();

        if (mb_strtolower($user->email) !== mb_strtolower($invitation->email)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'This invitation was sent to a different email address.',
            ]);

            return to_route('teams.index');
        }

        $team = $invitation->team;

        if ($team->roleFor($user) === null) {
            $team->members()->attach($user->id, ['role' => $invitation->role->value]);
        }

        $invitation->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "You've joined \"{$team->name}\".",
        ]);

        return to_route('teams.show', $team);
    }
}
