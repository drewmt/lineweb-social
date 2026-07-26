<?php

namespace Tests\Feature;

use App\Community\NotificationCenter;
use App\Enums\NotificationDigestFrequency;
use App\Jobs\SendDailyNotificationDigest;
use App\Mail\DailyNotificationDigest;
use App\Models\Comment;
use App\Models\NotificationPreference;
use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use App\Notifications\CommentReplyNotification;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DailyNotificationDigestTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_command_queues_only_verified_active_daily_members(): void
    {
        Carbon::setTestNow('2026-07-26 08:00:00');
        Queue::fake();

        $eligible = User::factory()->create();
        $this->enableDigest($eligible);

        $unverified = User::factory()->unverified()->create();
        $this->enableDigest($unverified);

        $suspended = User::factory()->create(['suspended_at' => now()]);
        $this->enableDigest($suspended);

        $off = User::factory()->create();
        $off->notificationPreference()->create([
            'email_digest_frequency' => NotificationDigestFrequency::Off,
        ]);

        $this->artisan('notifications:dispatch-digests')
            ->expectsOutput('Queued 1 notification digest job(s).')
            ->assertSuccessful();

        Queue::assertPushed(
            SendDailyNotificationDigest::class,
            fn (SendDailyNotificationDigest $job): bool => $job->userId === $eligible->id
                && $job->cutoff->equalTo(now()),
        );
        Queue::assertPushed(SendDailyNotificationDigest::class, 1);
    }

    public function test_daily_digest_sends_only_counts_and_advances_its_cursor(): void
    {
        $cutoff = CarbonImmutable::parse('2026-07-26 08:00:00');
        Carbon::setTestNow($cutoff);
        Mail::fake();

        $recipient = User::factory()->create();
        $actor = User::factory()->create(['name' => 'Private Actor']);
        $space = Space::factory()->for($recipient, 'owner')->create([
            'name' => 'Private Space Name',
        ]);
        $post = Post::factory()->for($space)->for($recipient, 'author')->create([
            'body' => 'Private post body that must stay out of email.',
        ]);
        $comment = Comment::factory()->for($post)->for($actor, 'author')->create([
            'body' => 'Private comment body that must stay out of email.',
        ]);
        $preferences = $this->enableDigest($recipient, $cutoff->subDay());
        Carbon::setTestNow($cutoff->subHour());
        $recipient->notify(new CommentReplyNotification($comment));
        Carbon::setTestNow($cutoff);

        $job = new SendDailyNotificationDigest($recipient->id, $cutoff);
        $job->handle(app(NotificationCenter::class));

        Mail::assertSent(
            DailyNotificationDigest::class,
            fn (DailyNotificationDigest $mail): bool => $mail->total === 1
                && $mail->counts['comment_reply'] === 1
                && $mail->counts['content_mention'] === 0
                && $mail->counts['space_moderation'] === 0
                && ! $mail->hasMore,
        );

        $html = (new DailyNotificationDigest([
            'comment_reply' => 1,
            'content_mention' => 0,
            'space_moderation' => 0,
        ], 1, false))->render();

        $this->assertStringContainsString('1 unread update', $html);
        $this->assertStringContainsString('Open notifications', $html);
        $this->assertStringNotContainsString('Private Actor', $html);
        $this->assertStringNotContainsString('Private Space Name', $html);
        $this->assertStringNotContainsString('Private post body', $html);
        $this->assertStringNotContainsString('Private comment body', $html);
        $this->assertTrue($preferences->refresh()->email_digest_cursor_at->equalTo($cutoff));
    }

    public function test_read_or_inaccessible_notifications_do_not_leave_the_server(): void
    {
        $cutoff = CarbonImmutable::parse('2026-07-26 08:00:00');
        Carbon::setTestNow($cutoff);
        Mail::fake();

        $recipient = User::factory()->create();
        $actor = User::factory()->create();
        $space = Space::factory()->for($recipient, 'owner')->create();
        $post = Post::factory()->for($space)->for($recipient, 'author')->create();
        $comment = Comment::factory()->for($post)->for($actor, 'author')->create();
        $preferences = $this->enableDigest($recipient, $cutoff->subDay());
        Carbon::setTestNow($cutoff->subHour());
        $recipient->notify(new CommentReplyNotification($comment));
        $recipient->notifications()->firstOrFail()->markAsRead();
        $recipient->notify(new CommentReplyNotification($comment));
        $comment->delete();
        Carbon::setTestNow($cutoff);

        (new SendDailyNotificationDigest($recipient->id, $cutoff))
            ->handle(app(NotificationCenter::class));

        Mail::assertNothingSent();
        $this->assertTrue($preferences->refresh()->email_digest_cursor_at->equalTo($cutoff));
    }

    public function test_high_volume_windows_continue_from_a_stable_notification_cursor(): void
    {
        $cutoff = CarbonImmutable::parse('2026-07-26 08:00:00');
        Carbon::setTestNow($cutoff);
        Mail::fake();

        $recipient = User::factory()->create();
        $actor = User::factory()->create();
        $space = Space::factory()->for($recipient, 'owner')->create();
        $post = Post::factory()->for($space)->for($recipient, 'author')->create();
        $comment = Comment::factory()->for($post)->for($actor, 'author')->create();
        $preferences = $this->enableDigest($recipient, $cutoff->subDay());
        Carbon::setTestNow($cutoff->subHour());

        foreach (range(1, 101) as $_) {
            $recipient->notify(new CommentReplyNotification($comment));
        }

        Carbon::setTestNow($cutoff);
        (new SendDailyNotificationDigest($recipient->id, $cutoff))
            ->handle(app(NotificationCenter::class));

        $preferences->refresh();
        $this->assertNotNull($preferences->email_digest_cursor_notification_id);
        $this->assertTrue($preferences->email_digest_cursor_at->equalTo($cutoff->subHour()));
        Mail::assertSent(
            DailyNotificationDigest::class,
            fn (DailyNotificationDigest $mail): bool => $mail->total === 100
                && $mail->hasMore,
        );

        (new SendDailyNotificationDigest($recipient->id, $cutoff))
            ->handle(app(NotificationCenter::class));

        Mail::assertSent(
            DailyNotificationDigest::class,
            fn (DailyNotificationDigest $mail): bool => $mail->total === 1
                && ! $mail->hasMore,
        );
        Mail::assertSent(DailyNotificationDigest::class, 2);
        $this->assertTrue($preferences->refresh()->email_digest_cursor_at->equalTo($cutoff));
        $this->assertNull($preferences->email_digest_cursor_notification_id);
    }

    public function test_transport_failure_leaves_the_delivery_cursor_for_a_retry(): void
    {
        $cutoff = CarbonImmutable::parse('2026-07-26 08:00:00');
        Carbon::setTestNow($cutoff);

        $recipient = User::factory()->create();
        $actor = User::factory()->create();
        $space = Space::factory()->for($recipient, 'owner')->create();
        $post = Post::factory()->for($space)->for($recipient, 'author')->create();
        $comment = Comment::factory()->for($post)->for($actor, 'author')->create();
        $originalCursor = $cutoff->subDay();
        $preferences = $this->enableDigest($recipient, $originalCursor);
        Carbon::setTestNow($cutoff->subHour());
        $recipient->notify(new CommentReplyNotification($comment));
        Carbon::setTestNow($cutoff);
        Mail::shouldReceive('to')
            ->once()
            ->with($recipient->email)
            ->andThrow(new \RuntimeException('Synthetic transport failure.'));

        try {
            (new SendDailyNotificationDigest($recipient->id, $cutoff))
                ->handle(app(NotificationCenter::class));
            $this->fail('The synthetic transport failure was not raised.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic transport failure.', $exception->getMessage());
        }

        $this->assertTrue($preferences->refresh()->email_digest_cursor_at->equalTo($originalCursor));
        $this->assertNull($preferences->email_digest_cursor_notification_id);
    }

    public function test_delivery_rechecks_current_consent_and_account_state(): void
    {
        $cutoff = CarbonImmutable::parse('2026-07-26 08:00:00');
        Carbon::setTestNow($cutoff);
        Mail::fake();

        $disabled = User::factory()->create();
        $disabledPreference = $this->enableDigest($disabled, $cutoff->subDay());
        $disabledPreference->forceFill([
            'email_digest_frequency' => NotificationDigestFrequency::Off,
            'email_digest_cursor_at' => null,
            'email_digest_cursor_notification_id' => null,
        ])->save();

        $suspended = User::factory()->create(['suspended_at' => now()]);
        $this->enableDigest($suspended, $cutoff->subDay());

        (new SendDailyNotificationDigest($disabled->id, $cutoff))
            ->handle(app(NotificationCenter::class));
        (new SendDailyNotificationDigest($suspended->id, $cutoff))
            ->handle(app(NotificationCenter::class));

        Mail::assertNothingSent();
        $this->assertNull($disabledPreference->refresh()->email_digest_cursor_at);
    }

    private function enableDigest(
        User $user,
        CarbonImmutable|string|null $cursor = null,
    ): NotificationPreference {
        $preferences = $user->notificationPreference()->create([
            'email_digest_frequency' => NotificationDigestFrequency::Daily,
        ]);
        $preferences->forceFill([
            'email_digest_cursor_at' => $cursor ?? now()->subDay(),
            'email_digest_cursor_notification_id' => null,
        ])->save();

        return $preferences;
    }
}
