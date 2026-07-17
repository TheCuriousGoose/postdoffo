<?php

namespace App\Http\Controllers;

use App\Models\WorkspaceInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkspaceInvitationController extends Controller
{
    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = WorkspaceInvitation::where('token', $token)->firstOrFail();
        $user = $request->user();

        if (mb_strtolower($user->email) !== mb_strtolower($invitation->email)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'This invitation was sent to a different email address.',
            ]);

            return to_route('workspaces.index');
        }

        $workspace = $invitation->workspace;

        if ($workspace->roleFor($user) === null) {
            $workspace->members()->attach($user->id, ['role' => $invitation->role->value]);
        }

        $invitation->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "You've joined \"{$workspace->name}\".",
        ]);

        return to_route('workspaces.show', $workspace);
    }
}
