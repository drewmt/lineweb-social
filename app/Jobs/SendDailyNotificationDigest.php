<?php

namespace App\Jobs;

use App\Community\NotificationCenter;
use App\Enums\NotificationDigestFrequency;
use App\Enums\NotificationType;
use App\Mail\DailyNotificationDigest;
use App\Models\NotificationPreference;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Mail;

class SendDailyNotificationDigest implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    private const MAX_CANDIDATES = 100;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 86400;

    public function __construct(
        public readonly int $userId,
        public readonly CarbonImmutable $cutoff,
    ) {
        $this->onQueue('notifications');
    }

    public function uniqueId(): string
    {
        return $this->userId.':'.$this->cutoff->toIso8601String();
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(NotificationCenter $notificationCenter): void
    {
        $preferences = NotificationPreference::query()
            ->with('user')
            ->whereKey($this->userId)
            ->first();

        if (! $this->canDeliver($preferences)) {
            return;
        }

        $cursor = $preferences->email_digest_cursor_at;

        if ($cursor === null || ! $cursor->lt($this->cutoff)) {
            return;
        }

        $notifications = DatabaseNotification::query()
            ->where('notifiable_type', $preferences->user->getMorphClass())
            ->where('notifiable_id', $preferences->user_id)
            ->whereNull('read_at')
            ->where(function ($notifications) use ($cursor, $preferences): void {
                $notifications->where('created_at', '>', $cursor);

                if ($preferences->email_digest_cursor_notification_id === null) {
                    $notifications->orWhere('created_at', $cursor);

                    return;
                }

                $notifications->orWhere(function ($notifications) use ($cursor, $preferences): void {
                    $notifications
                        ->where('created_at', $cursor)
                        ->where('id', '>', $preferences->email_digest_cursor_notification_id);
                });
            })
            ->where('created_at', '<', $this->cutoff)
            ->oldest('created_at')
            ->orderBy('id')
            ->limit(self::MAX_CANDIDATES + 1)
            ->get();
        $hasMore = $notifications->count() > self::MAX_CANDIDATES;
        $batch = $notifications->take(self::MAX_CANDIDATES);
        $counts = [
            NotificationType::CommentReply->value => 0,
            NotificationType::ContentMention->value => 0,
            NotificationType::SpaceModeration->value => 0,
        ];

        /** @var DatabaseNotification $notification */
        foreach ($batch as $notification) {
            $kind = $notificationCenter->availableKind($preferences->user, $notification);

            if ($kind !== null) {
                $counts[$kind->value]++;
            }
        }

        $total = array_sum($counts);

        if ($total > 0) {
            if (! $this->deliveryStillAllowed($preferences)) {
                return;
            }

            Mail::to($preferences->user->email)->send(
                new DailyNotificationDigest($counts, $total, $hasMore),
            );
        }

        $lastCandidate = $batch->last();
        $nextCursorAt = $hasMore && $lastCandidate instanceof DatabaseNotification
            ? $lastCandidate->created_at
            : $this->cutoff;
        $nextCursorNotificationId = $hasMore && $lastCandidate instanceof DatabaseNotification
            ? (string) $lastCandidate->getKey()
            : null;

        NotificationPreference::query()
            ->whereKey($preferences->user_id)
            ->where('email_digest_frequency', NotificationDigestFrequency::Daily->value)
            ->where('email_digest_cursor_at', $preferences->email_digest_cursor_at)
            ->where('email_digest_cursor_notification_id', $preferences->email_digest_cursor_notification_id)
            ->update([
                'email_digest_cursor_at' => $nextCursorAt,
                'email_digest_cursor_notification_id' => $nextCursorNotificationId,
            ]);
    }

    private function canDeliver(?NotificationPreference $preferences): bool
    {
        if (! $preferences instanceof NotificationPreference
            || $preferences->email_digest_frequency !== NotificationDigestFrequency::Daily) {
            return false;
        }

        return $preferences->user->hasVerifiedEmail()
            && ! $preferences->user->isSuspended();
    }

    private function deliveryStillAllowed(NotificationPreference $preferences): bool
    {
        return NotificationPreference::query()
            ->whereKey($preferences->user_id)
            ->where('email_digest_frequency', NotificationDigestFrequency::Daily->value)
            ->where('email_digest_cursor_at', $preferences->email_digest_cursor_at)
            ->where('email_digest_cursor_notification_id', $preferences->email_digest_cursor_notification_id)
            ->whereHas('user', fn ($users) => $users
                ->whereNotNull('email_verified_at')
                ->whereNull('suspended_at'))
            ->exists();
    }
}
