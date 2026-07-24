<?php

namespace Tests\Feature\Settings;

use App\Models\Comment;
use App\Models\Conversation;
use App\Models\DirectMessage;
use App\Models\NotificationPreference;
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
        Comment::factory()->create([
            'post_id' => $post->getKey(),
            'user_id' => $user->getKey(),
            'body' => 'My exported comment',
        ]);

        UserFollow::query()->create([
            'follower_id' => $user->getKey(),
            'followed_id' => $other->getKey(),
        ]);
        NotificationPreference::query()->create([
            'user_id' => $user->getKey(),
            'comment_replies' => false,
            'content_mentions' => true,
            'space_moderation' => true,
        ]);

        $conversation = Conversation::between($user, $other);
        DirectMessage::query()->create([
            'conversation_id' => $conversation->getKey(),
            'sender_id' => $user->getKey(),
            'body' => 'My exported message',
        ]);
        DirectMessage::query()->create([
            'conversation_id' => $conversation->getKey(),
            'sender_id' => $other->getKey(),
            'body' => 'Someone else private message',
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
        $this->assertSame('community-member', $export['following'][0]['handle']);
        $this->assertSame('My exported message', $export['direct_messages'][0]['body']);
        $this->assertSame('My phone', $export['security']['api_tokens'][0]['name']);
        $this->assertFalse($export['notification_preferences']['comment_replies']);
        $this->assertSame('sent', $export['space_invitation_activity'][0]['relationship']);
        $this->assertSame('content.mentioned', $export['notifications'][0]['type']);

        $this->assertStringNotContainsString('Someone else private message', $content);
        $this->assertStringNotContainsString('private-recipient@example.com', $content);
        $this->assertStringNotContainsString(hash('sha256', 'private-invitation-token'), $content);
        $this->assertStringNotContainsString('private-two-factor-secret', $content);
        $this->assertStringNotContainsString('private-recovery-codes', $content);
        $this->assertStringNotContainsString($user->password, $content);
        $this->assertStringNotContainsString($token->accessToken->token, $content);
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
