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
     * Owner and editor can create/update/delete collections, requests, environments.
     */
    public function edit(User $user, Workspace $workspace): bool
    {
        return $workspace->roleFor($user)?->canEdit() ?? false;
    }

    /**
     * Only the owner can rename/delete the workspace itself.
     */
    public function update(User $user, Workspace $workspace): bool
    {
        return $workspace->roleFor($user) === WorkspaceRole::Owner;
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        return $workspace->roleFor($user) === WorkspaceRole::Owner;
    }

    public function manageMembers(User $user, Workspace $workspace): bool
    {
        return $workspace->roleFor($user)?->canManageMembers() ?? false;
    }
}
