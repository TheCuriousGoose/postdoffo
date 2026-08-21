<?php

namespace App\Notifications;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TeamMemberAddedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Team $team,
        private readonly User $addedBy,
        private readonly TeamRole $role,
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
            'message' => "{$this->addedBy->name} added you to \"{$this->team->name}\" as {$this->role->label()}.",
            'team_id' => $this->team->id,
            'team_name' => $this->team->name,
            'role' => $this->role->value,
            'added_by' => $this->addedBy->name,
        ];
    }
}
