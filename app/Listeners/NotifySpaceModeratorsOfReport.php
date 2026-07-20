<?php

namespace App\Listeners;

use App\Enums\SpaceRole;
use App\Events\CommentReported;
use App\Events\PostReported;
use App\Models\CommentReport;
use App\Models\NotificationPreference;
use App\Models\PostReport;
use App\Notifications\SpaceModerationNotification;
use Illuminate\Support\Facades\Notification;

class NotifySpaceModeratorsOfReport
{
    public function handlePostReported(PostReported $event): void
    {
        $this->notify($event->report, 'post');
    }

    public function handleCommentReported(CommentReported $event): void
    {
        $this->notify($event->report, 'comment');
    }

    private function notify(PostReport|CommentReport $report, string $kind): void
    {
        $report->loadMissing('space');
        $recipients = $report->space->members()
            ->wherePivotIn('role', [SpaceRole::Owner->value, SpaceRole::Moderator->value])
            ->when(
                $report->reporter_id !== null,
                fn ($query) => $query->whereKeyNot($report->reporter_id),
            )
            ->with('notificationPreference')
            ->get()
            ->filter(fn ($user): bool => ! ($user->notificationPreference instanceof NotificationPreference)
                || $user->notificationPreference->space_moderation);

        Notification::send(
            $recipients,
            new SpaceModerationNotification($report->space_id, $kind, $report->id),
        );
    }
}
