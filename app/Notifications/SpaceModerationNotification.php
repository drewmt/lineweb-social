<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use Illuminate\Notifications\Notification;

class SpaceModerationNotification extends Notification
{
    public function __construct(
        private readonly int $spaceId,
        private readonly string $reportKind,
        private readonly int $reportId,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return NotificationType::SpaceModeration->value;
    }

    /** @return array{space_id: int, report_kind: string, report_id: int} */
    public function toDatabase(object $notifiable): array
    {
        return [
            'space_id' => $this->spaceId,
            'report_kind' => $this->reportKind,
            'report_id' => $this->reportId,
        ];
    }
}
