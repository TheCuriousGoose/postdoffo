<?php

namespace App\Notifications;

use App\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly TeamInvitation $invitation) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $team = $this->invitation->team;
        $inviter = $this->invitation->invitedBy;

        return (new MailMessage)
            ->subject("{$inviter->name} invited you to the \"{$team->name}\" team")
            ->greeting('You have been invited!')
            ->line("{$inviter->name} ({$inviter->email}) invited you to join the \"{$team->name}\" team as {$this->invitation->role->label()}.")
            ->line('Joining gives you access to every workspace this team owns.')
            ->action('Accept invitation', route('team-invitations.accept', $this->invitation->token))
            ->line('If you were not expecting this invitation, you can safely ignore this email.');
    }
}
