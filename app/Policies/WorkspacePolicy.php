<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    /**
     * Any member (owner, editor, or viewer) can view the workspace.
     */
    public function view(User $user, Workspace $workspace): bool
    {
        return $workspace->roleFor($user) !== null;
    }

    /**
     * Owner, co-owner and editor can create/update/delete collections,
     * requests, environments.
     */
    public function edit(User $user, Workspace $workspace): bool
    {
        return $workspace->roleFor($user)?->canEdit() ?? false;
    }

    /**
     * Owner and co-owner can rename the workspace.
     */
    public function update(User $user, Workspace $workspace): bool
    {
        return $workspace->roleFor($user)?->canManageWorkspace() ?? false;
    }

    /**
     * Deleting the whole workspace stays with the one real owner — a co-owner
     * can hand out and revoke access, but not destroy everyone's work.
     */
    public function delete(User $user, Workspace $workspace): bool
    {
        return $workspace->roleFor($user) === WorkspaceRole::Owner;
    }

    /**
     * Owner and co-owner can invite, re-role and remove members.
     */
    public function manageMembers(User $user, Workspace $workspace): bool
    {
        return $workspace->roleFor($user)?->canManageMembers() ?? false;
    }
}
