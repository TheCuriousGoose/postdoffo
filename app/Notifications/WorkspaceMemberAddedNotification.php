<?php

namespace App\Notifications;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WorkspaceMemberAddedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Workspace $workspace,
        private readonly User $addedBy,
        private readonly WorkspaceRole $role,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => "{$this->addedBy->name} added you to \"{$this->workspace->name}\" as {$this->role->label()}.",
            'workspace_id' => $this->workspace->id,
            'workspace_name' => $this->workspace->name,
            'role' => $this->role->value,
            'added_by' => $this->addedBy->name,
        ];
    }
}
