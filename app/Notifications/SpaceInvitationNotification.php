<?php

namespace App\Notifications;

use App\Models\Space;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SpaceInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Space $space,
        public readonly User $inviter,
        public readonly string $acceptUrl,
        public readonly CarbonInterface $expiresAt,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("You're invited to {$this->space->name}")
            ->greeting('You have a new Space invitation')
            ->line("{$this->inviter->name} invited you to join {$this->space->name}.")
            ->action('View invitation', $this->acceptUrl)
            ->line('The invitation expires '.$this->expiresAt->toFormattedDateString().'.')
            ->line('If you were not expecting this invitation, you can ignore this email.');
    }
}
