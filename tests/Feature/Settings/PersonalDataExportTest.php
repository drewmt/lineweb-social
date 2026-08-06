<?php

namespace Tests\Feature\Settings;

use App\Enums\NotificationDigestFrequency;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\DirectMessage;
use App\Models\DirectMessageReport;
use App\Models\NotificationPreference;
use App\Models\PlatformAppeal;
use App\Models\Post;
use App\Models\Space;
use App\Models\SpaceInvitation;
use App\Models\User;
use App\Models\UserFollow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PersonalDataExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_member_with_recent_password_confirmation_can_download_their_data(): void
    {
        $user = User::factory()->create([
            'name' => 'Andrew Matia',
            'handle' => 'andrew-matia',
            'email' => 'andrew@example.com',
            'two_factor_secret' => 'private-two-factor-secret',
            'two_factor_recovery_codes' => 'private-recovery-codes',
        ]);
        $other = User::factory()->create(['handle' => 'community-member']);
        $space = Space::factory()->for($other, 'owner')->create(['slug' => 'makers-circle']);
        $space->addMember($user);

        $post = Post::factory()->create([
            'space_id' => $space->getKey(),
            'user_id' => $user->getKey(),
            'body' => 'My exported post',
        ]);
        $parent = Comment::factory()->create([
            'post_id' => $post->getKey(),
            'user_id' => $other->getKey(),
            'body' => 'Another member private parent body',
        ]);
        Comment::factory()->create([
            'post_id' => $post->getKey(),
            'user_id' => $user->getKey(),
            'parent_id' => $parent->getKey(),
            'body' => 'My exported comment',
        ]);

        UserFollow::query()->create([
            'follower_id' => $user->getKey(),
            'followed_id' => $other->getKey(),
        ]);
        $notificationPreference = NotificationPreference::query()->create([
            'user_id' => $user->getKey(),
            'comment_replies' => false,
            'content_mentions' => true,
            'space_moderation' => true,
            'email_digest_frequency' => NotificationDigestFrequency::Daily,
        ]);
        $notificationPreference->forceFill([
            'email_digest_cursor_at' => now()->subHour(),
            'email_digest_cursor_notification_id' => (string) Str::uuid(),
        ])->save();

        $conversation = Conversation::between($user, $other);
        DirectMessage::query()->create([
            'conversation_id' => $conversation->getKey(),
            'sender_id' => $user->getKey(),
            'body' => 'My exported message',
        ]);
        $incomingMessage = DirectMessage::query()->create([
            'conversation_id' => $conversation->getKey(),
            'sender_id' => $other->getKey(),
            'body' => 'Someone else private message',
        ]);
        DirectMessageReport::query()->create([
            'direct_message_id' => $incomingMessage->getKey(),
            'reporter_id' => $user->getKey(),
            'reported_user_id' => $other->getKey(),
            'reason' => 'harassment',
            'details' => 'My submitted report context',
            'message_body_snapshot' => $incomingMessage->body,
            'message_sent_at' => $incomingMessage->created_at,
            'status' => 'resolved',
            'reviewed_at' => now(),
            'reviewer_note' => 'Private administrator decision context',
        ]);
        $reviewer = User::factory()->create([
            'name' => 'Private Appeal Reviewer',
            'email' => 'private-reviewer@example.com',
        ]);
        PlatformAppeal::query()->create([
            'user_id' => $user->getKey(),
            'suspension_reference' => (string) Str::uuid(),
            'suspension_started_at' => now()->subDay(),
            'status' => 'approved',
            'statement' => 'My account appeal statement',
            'decision_message' => 'Your account appeal was approved.',
            'reviewed_by' => $reviewer->getKey(),
            'reviewed_at' => now(),
        ]);

        $token = $user->createToken(
            'My phone',
            ['profile:read'],
            now()->addDays(30),
        );
        SpaceInvitation::query()->create([
            'space_id' => $space->getKey(),
            'invited_by' => $user->getKey(),
            'email' => 'private-recipient@example.com',
            'role' => 'member',
            'token_hash' => hash('sha256', 'private-invitation-token'),
            'expires_at' => now()->addWeek(),
        ]);
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'content.mentioned',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->getKey(),
            'data' => json_encode(['actor_id' => $other->getKey()], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('personal-data.export'));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/json')
            ->assertHeader('content-disposition');

        $content = $response->streamedContent();
        $export = json_decode($content, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(1, $export['export_version']);
        $this->assertSame('andrew@example.com', $export['account']['email']);
        $this->assertSame('makers-circle', $export['space_memberships'][0]['space_slug']);
        $this->assertSame('My exported post', $export['posts'][0]['body']);
        $this->assertSame('My exported comment', $export['comments'][0]['body']);
        $this->assertSame($parent->getKey(), $export['comments'][0]['parent_id']);
        $this->assertSame('community-member', $export['following'][0]['handle']);
        $this->assertSame('My exported message', $export['direct_messages'][0]['body']);
        $this->assertSame(
            'Someone else private message',
            $export['submitted_direct_message_reports'][0]['message_body_snapshot'],
        );
        $this->assertSame(
            'My submitted report context',
            $export['submitted_direct_message_reports'][0]['details'],
        );
        $this->assertSame(
            'My account appeal statement',
            $export['account_appeals'][0]['statement'],
        );
        $this->assertSame(
            'Your account appeal was approved.',
            $export['account_appeals'][0]['decision_message'],
        );
        $this->assertSame('My phone', $export['security']['api_tokens'][0]['name']);
        $this->assertFalse($export['notification_preferences']['comment_replies']);
        $this->assertSame(
            NotificationDigestFrequency::Daily->value,
            $export['notification_preferences']['email_digest_frequency'],
        );
        $this->assertSame('sent', $export['space_invitation_activity'][0]['relationship']);
        $this->assertSame('content.mentioned', $export['notifications'][0]['type']);

        $this->assertStringNotContainsString(
            'Private administrator decision context',
            $content,
        );
        $this->assertStringNotContainsString('Another member private parent body', $content);
        $this->assertStringNotContainsString('Private Appeal Reviewer', $content);
        $this->assertStringNotContainsString('private-reviewer@example.com', $content);
        $this->assertStringNotContainsString('private-recipient@example.com', $content);
        $this->assertStringNotContainsString(hash('sha256', 'private-invitation-token'), $content);
        $this->assertStringNotContainsString('private-two-factor-secret', $content);
        $this->assertStringNotContainsString('private-recovery-codes', $content);
        $this->assertStringNotContainsString($user->password, $content);
        $this->assertStringNotContainsString($token->accessToken->token, $content);
        $this->assertStringNotContainsString('email_digest_cursor_at', $content);
        $this->assertStringNotContainsString('email_digest_cursor_notification_id', $content);
    }

    public function test_data_export_requires_a_verified_account_and_recent_password_confirmation(): void
    {
        $unverified = User::factory()->unverified()->create();

        $this->actingAs($unverified)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('personal-data.export'))
            ->assertRedirect(route('verification.notice'));

        $this->flushSession();
        $verified = User::factory()->create();

        $this->actingAs($verified)
            ->get(route('personal-data.export'))
            ->assertRedirect(route('password.confirm'));
    }

    public function test_data_export_does_not_silently_truncate_authored_content(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user, 'owner')->create();
        Post::factory()->count(525)->create([
            'space_id' => $space->getKey(),
            'user_id' => $user->getKey(),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('personal-data.export'));

        $export = json_decode(
            $response->streamedContent(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertCount(525, $export['posts']);
    }
}
