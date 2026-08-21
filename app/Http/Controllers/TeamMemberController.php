<?php

namespace App\Http\Controllers;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamMember;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use App\Notifications\TeamMemberAddedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TeamMemberController extends Controller
{
    public function index(Team $team): JsonResponse
    {
        $this->authorize('view', $team);

        return response()->json($this->payload($team));
    }

    public function store(Request $request, Team $team): JsonResponse
    {
        $this->authorize('manageMembers', $team);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(TeamRole::assignableValues())],
        ]);

        $email = mb_strtolower($data['email']);
        $owner = $team->owner;

        if ($email === mb_strtolower($owner->email)) {
            throw ValidationException::withMessages(['email' => 'This user already owns the team.']);
        }

        $existingUser = User::whereRaw('lower(email) = ?', [$email])->first();

        if ($existingUser && $team->roleFor($existingUser) !== null) {
            throw ValidationException::withMessages(['email' => 'This user is already a member of the team.']);
        }

        if ($team->invitations()->whereRaw('lower(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages(['email' => 'An invitation has already been sent to this email.']);
        }

        if ($existingUser) {
            $team->members()->attach($existingUser->id, ['role' => $data['role']]);

            $existingUser->notify(new TeamMemberAddedNotification(
                $team,
                $request->user(),
                TeamRole::from($data['role']),
            ));
        } else {
            $invitation = $team->invitations()->create([
                'email' => $data['email'],
                'role' => $data['role'],
                'invited_by_id' => $request->user()->id,
            ]);

            Notification::route('mail', $invitation->email)
                ->notify(new TeamInvitationNotification($invitation));
        }

        return response()->json($this->payload($team));
    }

    public function updateRole(Request $request, Team $team, User $member): JsonResponse
    {
        $this->authorize('manageMembers', $team);

        abort_if($member->id === $team->owner_id, 422, "The team owner's role cannot be changed.");

        $currentRole = $team->roleFor($member);
        abort_if($currentRole === null, 404);
        $this->guardAdminTarget($request, $team, $currentRole, 'change');

        $data = $request->validate([
            'role' => ['required', Rule::in(TeamRole::assignableValues())],
        ]);

        $team->members()->updateExistingPivot($member->id, ['role' => $data['role']]);

        return response()->json($this->payload($team));
    }

    public function destroy(Request $request, Team $team, User $member): JsonResponse
    {
        abort_if($member->id === $team->owner_id, 422, 'The team owner cannot be removed.');

        $isSelf = $member->id === $request->user()->id;

        if (! $isSelf) {
            $this->authorize('manageMembers', $team);
        }

        $currentRole = $team->roleFor($member);
        abort_if($currentRole === null, 404);

        // Leaving of your own accord is always allowed, admin or not.
        if (! $isSelf) {
            $this->guardAdminTarget($request, $team, $currentRole, 'remove');
        }

        $team->members()->detach($member->id);

        return response()->json(status: 204);
    }

    /**
     * Admins can manage everyone below them, but only the owner can demote or
     * remove a fellow admin. Without this, two admins could strip each other's
     * access in a race, and one could quietly push out the person the owner
     * appointed alongside them — mirrors WorkspaceMemberController's co-owner guard.
     */
    private function guardAdminTarget(
        Request $request,
        Team $team,
        TeamRole $targetRole,
        string $verb,
    ): void {
        if ($targetRole !== TeamRole::Admin) {
            return;
        }

        abort_if(
            $team->roleFor($request->user()) !== TeamRole::Owner,
            403,
            "Only the team owner can {$verb} an admin.",
        );
    }

    public function destroyInvitation(Team $team, TeamInvitation $invitation): JsonResponse
    {
        $this->authorize('manageMembers', $team);

        abort_if($invitation->team_id !== $team->id, 404);

        $invitation->delete();

        return response()->json(status: 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Team $team): array
    {
        $owner = $team->owner;

        $members = collect([[
            'id' => $owner->id,
            'name' => $owner->name,
            'email' => $owner->email,
            'role' => TeamRole::Owner->value,
        ]])->concat(
            TeamMember::with('user')->where('team_id', $team->id)->get()
                ->map(fn (TeamMember $membership) => [
                    'id' => $membership->user->id,
                    'name' => $membership->user->name,
                    'email' => $membership->user->email,
                    'role' => $membership->role->value,
                ])
        )->values();

        $invitations = $team->invitations()->orderBy('created_at')->get()
            ->map(fn (TeamInvitation $invitation) => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'created_at' => $invitation->created_at,
                // The direct accept link, so the owner can share access even
                // when invitation email delivery isn't set up.
                'url' => route('team-invitations.accept', $invitation->token),
            ])->values();

        return [
            'members' => $members,
            'invitations' => $invitations,
        ];
    }
}
