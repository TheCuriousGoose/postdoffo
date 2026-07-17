<?php

namespace App\Enums;

enum WorkspaceRole: string
{
    case Owner = 'owner';
    case Editor = 'editor';
    case Viewer = 'viewer';

    public function canEdit(): bool
    {
        return $this !== self::Viewer;
    }

    public function canManageMembers(): bool
    {
        return $this === self::Owner;
    }
}
