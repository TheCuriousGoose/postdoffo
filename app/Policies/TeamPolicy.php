<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    /**
     * Any member (owner, admin, or member) can view the team.
     */
    public function view(User $user, Team $team): bool
    {
        return $team->roleFor($user) !== null;
    }

    /**
     * Owner and admin can rename the team.
     */
    public function update(User $user, Team $team): bool
    {
        return $team->roleFor($user)?->canManageMembers() ?? false;
    }

    /**
     * Deleting the whole team stays with the one real owner — an admin can
     * hand out and revoke access and manage workspaces, but not dissolve the
     * organization out from under everyone.
     */
    public function delete(User $user, Team $team): bool
    {
        return $team->roleFor($user) === TeamRole::Owner;
    }

    /**
     * Owner and admin can invite, re-role and remove members.
     */
    public function manageMembers(User $user, Team $team): bool
    {
        return $team->roleFor($user)?->canManageMembers() ?? false;
    }

    /**
     * Owner and admin can create workspaces in the team and attach/detach
     * existing ones.
     */
    public function manageWorkspaces(User $user, Team $team): bool
    {
        return $team->roleFor($user)?->canManageWorkspaces() ?? false;
    }
}
