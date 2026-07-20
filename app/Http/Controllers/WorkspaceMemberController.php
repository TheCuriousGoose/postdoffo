<?php

namespace App\Http\Controllers;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use App\Notifications\WorkspaceInvitationNotification;
use App\Notifications\WorkspaceMemberAddedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkspaceMemberController extends Controller
{
    public function index(Workspace $workspace): JsonResponse
    {
        $this->authorize('view', $workspace);

        return response()->json($this->payload($workspace));
    }

    public function store(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('manageMembers', $workspace);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in([WorkspaceRole::Editor->value, WorkspaceRole::Viewer->value])],
        ]);

        $email = mb_strtolower($data['email']);
        $owner = $workspace->owner;

        if ($email === mb_strtolower($owner->email)) {
            throw ValidationException::withMessages(['email' => 'This user already owns the workspace.']);
        }

        $existingUser = User::whereRaw('lower(email) = ?', [$email])->first();

        if ($existingUser && $workspace->roleFor($existingUser) !== null) {
            throw ValidationException::withMessages(['email' => 'This user is already a member of the workspace.']);
        }

        if ($workspace->invitations()->whereRaw('lower(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages(['email' => 'An invitation has already been sent to this email.']);
        }

        if ($existingUser) {
            $workspace->members()->attach($existingUser->id, ['role' => $data['role']]);

            $existingUser->notify(new WorkspaceMemberAddedNotification(
                $workspace,
                $request->user(),
                WorkspaceRole::from($data['role']),
            ));
        } else {
            $invitation = $workspace->invitations()->create([
                'email' => $data['email'],
                'role' => $data['role'],
                'invited_by_id' => $request->user()->id,
            ]);

            Notification::route('mail', $invitation->email)
                ->notify(new WorkspaceInvitationNotification($invitation));
        }

        return response()->json($this->payload($workspace));
    }

    public function updateRole(Request $request, Workspace $workspace, User $member): JsonResponse
    {
        $this->authorize('manageMembers', $workspace);

        abort_if($member->id === $workspace->owner_id, 422, "The workspace owner's role cannot be changed.");
        abort_if($workspace->roleFor($member) === null, 404);

        $data = $request->validate([
            'role' => ['required', Rule::in([WorkspaceRole::Editor->value, WorkspaceRole::Viewer->value])],
        ]);

        $workspace->members()->updateExistingPivot($member->id, ['role' => $data['role']]);

        return response()->json($this->payload($workspace));
    }

    public function destroy(Request $request, Workspace $workspace, User $member): JsonResponse
    {
        abort_if($member->id === $workspace->owner_id, 422, 'The workspace owner cannot be removed.');

        if ($member->id !== $request->user()->id) {
            $this->authorize('manageMembers', $workspace);
        }

        abort_if($workspace->roleFor($member) === null, 404);

        $workspace->members()->detach($member->id);

        return response()->json(status: 204);
    }

    public function destroyInvitation(Workspace $workspace, WorkspaceInvitation $invitation): JsonResponse
    {
        $this->authorize('manageMembers', $workspace);

        abort_if($invitation->workspace_id !== $workspace->id, 404);

        $invitation->delete();

        return response()->json(status: 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Workspace $workspace): array
    {
        $owner = $workspace->owner;

        $members = collect([[
            'id' => $owner->id,
            'name' => $owner->name,
            'email' => $owner->email,
            'role' => WorkspaceRole::Owner->value,
        ]])->concat(
            WorkspaceMember::with('user')->where('workspace_id', $workspace->id)->get()
                ->map(fn (WorkspaceMember $membership) => [
                    'id' => $membership->user->id,
                    'name' => $membership->user->name,
                    'email' => $membership->user->email,
                    'role' => $membership->role->value,
                ])
        )->values();

        $invitations = $workspace->invitations()->orderBy('created_at')->get()
            ->map(fn (WorkspaceInvitation $invitation) => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'created_at' => $invitation->created_at,
                // The direct accept link, so the owner can share access even
                // when invitation email delivery isn't set up.
                'url' => route('invitations.accept', $invitation->token),
            ])->values();

        return [
            'members' => $members,
            'invitations' => $invitations,
        ];
    }
}
