<?php

namespace App\Notifications;

use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public TeamInvitation $invitation,
        public string $token,
        public ?User $invitedBy = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('team-invitations.show', ['token' => $this->token]);
        $expiresAt = $this->invitation->expires_at?->toDayDateTimeString() ?? 'soon';
        $message = (new MailMessage)
            ->subject('You have been invited to join '.$this->invitation->team->name)
            ->greeting('Hello '.($this->invitation->name ?: 'there'))
            ->line(($this->invitedBy?->name ?? 'A teammate').' invited you to join '.$this->invitation->team->name.' as '.$this->invitation->role.'.');

        if (filled($this->invitation->message)) {
            $message->line($this->invitation->message);
        }

        return $message
            ->line('If you already have an account with this email, you can accept immediately. Otherwise, you can create one during the acceptance step.')
            ->action('Review invitation', $url)
            ->line('This invitation expires on '.$expiresAt.'.');
    }
}
