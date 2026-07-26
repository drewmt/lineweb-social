<?php

namespace Tests\Feature\Settings;

use App\Enums\NotificationDigestFrequency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_preferences_default_to_enabled_and_can_be_updated(): void
    {
        Carbon::setTestNow('2026-07-26 09:00:00');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('notification-preferences.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/notifications')
                ->where('preferences.commentReplies', true)
                ->where('preferences.contentMentions', true)
                ->where('preferences.spaceModeration', true)
                ->where('preferences.emailDigestFrequency', 'off'));

        $this->actingAs($user)
            ->patch(route('notification-preferences.update'), [
                'comment_replies' => false,
                'content_mentions' => false,
                'space_moderation' => false,
                'email_digest_frequency' => NotificationDigestFrequency::Daily->value,
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Notification preferences saved.');

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'comment_replies' => false,
            'content_mentions' => false,
            'space_moderation' => false,
            'email_digest_frequency' => NotificationDigestFrequency::Daily->value,
            'email_digest_cursor_at' => now(),
            'email_digest_cursor_notification_id' => null,
        ]);

        $this->actingAs($user)
            ->patch(route('notification-preferences.update'), [
                'comment_replies' => true,
                'content_mentions' => true,
                'space_moderation' => true,
                'email_digest_frequency' => NotificationDigestFrequency::Off->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'email_digest_frequency' => NotificationDigestFrequency::Off->value,
            'email_digest_cursor_at' => null,
            'email_digest_cursor_notification_id' => null,
        ]);
    }

    public function test_notification_preference_input_is_complete_and_boolean(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('notification-preferences.update'), [
                'comment_replies' => 'sometimes',
                'email_digest_frequency' => 'hourly',
            ])
            ->assertSessionHasErrors([
                'comment_replies',
                'content_mentions',
                'space_moderation',
                'email_digest_frequency',
            ]);

        $this->assertDatabaseCount('notification_preferences', 0);
    }

    public function test_unverified_accounts_cannot_open_notification_settings(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('notification-preferences.edit'))
            ->assertRedirect(route('verification.notice'));
    }
}
