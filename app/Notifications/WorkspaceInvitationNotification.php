<?php

namespace App\Notifications;

use App\Models\WorkspaceInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly WorkspaceInvitation $invitation) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $workspace = $this->invitation->workspace;
        $inviter = $this->invitation->invitedBy;

        return (new MailMessage)
            ->subject("{$inviter->name} invited you to the \"{$workspace->name}\" workspace")
            ->greeting('You have been invited!')
            ->line("{$inviter->name} ({$inviter->email}) invited you to join the \"{$workspace->name}\" workspace as {$this->invitation->role->label()}.")
            ->action('Accept invitation', route('invitations.accept', $this->invitation->token))
            ->line('If you were not expecting this invitation, you can safely ignore this email.');
    }
}
