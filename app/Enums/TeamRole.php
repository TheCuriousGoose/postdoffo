<?php

namespace App\Enums;

use App\Models\Workspace;

enum TeamRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    /**
     * The roles a team can hand out. `Owner` is not among them: it is derived
     * from `teams.owner_id` rather than stored on a membership, and there is
     * exactly one of it — mirrors {@see WorkspaceRole::assignableValues()}.
     *
     * @return array<int, string>
     */
    public static function assignableValues(): array
    {
        return [
            self::Admin->value,
            self::Member->value,
        ];
    }

    /** Human-readable name, for notification copy and the UI. */
    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::Member => 'Member',
        };
    }

    /**
     * Owner and admin can invite, re-role and remove team members, and can
     * create/attach/detach the workspaces the team owns. Only the owner may
     * demote or remove a fellow admin — enforced in TeamMemberController, the
     * same shape as the co-owner guard in WorkspaceMemberController.
     */
    public function canManageMembers(): bool
    {
        return $this === self::Owner || $this === self::Admin;
    }

    public function canManageWorkspaces(): bool
    {
        return $this === self::Owner || $this === self::Admin;
    }

    /**
     * What this team role is worth on a workspace the team owns — this is
     * the whole point of a team being an organization rather than a plain
     * directory: joining it grants access, it isn't just easier inviting.
     * Consumed by {@see Workspace::roleFor()}.
     */
    public function asWorkspaceRole(): WorkspaceRole
    {
        return match ($this) {
            self::Owner, self::Admin => WorkspaceRole::CoOwner,
            self::Member => WorkspaceRole::Editor,
        };
    }
}
