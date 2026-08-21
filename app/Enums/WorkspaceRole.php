<?php

namespace App\Enums;

use App\Policies\WorkspacePolicy;

enum WorkspaceRole: string
{
    case Owner = 'owner';
    case CoOwner = 'co_owner';
    case Editor = 'editor';
    case Viewer = 'viewer';

    /**
     * The roles a workspace can hand out. `Owner` is not among them: it is
     * derived from `workspaces.owner_id` rather than stored on a membership,
     * and there is exactly one of it.
     *
     * @return array<int, string>
     */
    public static function assignableValues(): array
    {
        return [
            self::CoOwner->value,
            self::Editor->value,
            self::Viewer->value,
        ];
    }

    /** Human-readable name, for notification copy and the UI. */
    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::CoOwner => 'Co-owner',
            self::Editor => 'Editor',
            self::Viewer => 'Viewer',
        };
    }

    public function canEdit(): bool
    {
        return $this !== self::Viewer;
    }

    /**
     * Co-owners share the owner's ability to invite people and manage who has
     * access — that is the point of the role. They cannot touch the owner's own
     * membership, and only the owner may demote or remove another co-owner;
     * both of those are enforced in WorkspaceMemberController.
     */
    public function canManageMembers(): bool
    {
        return $this === self::Owner || $this === self::CoOwner;
    }

    /**
     * Renaming the workspace. Deleting it stays owner-only — see
     * {@see WorkspacePolicy::delete()}.
     */
    public function canManageWorkspace(): bool
    {
        return $this === self::Owner || $this === self::CoOwner;
    }

    /**
     * Ordering used to pick the better of two roles the same person holds on
     * one workspace — e.g. an explicit per-workspace invite plus whatever
     * their team membership grants (see Workspace::roleFor()). Higher wins.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Owner => 3,
            self::CoOwner => 2,
            self::Editor => 1,
            self::Viewer => 0,
        };
    }

    public static function higherOf(self $a, self $b): self
    {
        return $a->rank() >= $b->rank() ? $a : $b;
    }
}
