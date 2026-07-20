<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_preferences_default_to_enabled_and_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('notification-preferences.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/notifications')
                ->where('preferences.commentReplies', true)
                ->where('preferences.spaceModeration', true));

        $this->actingAs($user)
            ->patch(route('notification-preferences.update'), [
                'comment_replies' => false,
                'space_moderation' => false,
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Notification preferences saved.');

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'comment_replies' => false,
            'space_moderation' => false,
        ]);
    }

    public function test_notification_preference_input_is_complete_and_boolean(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('notification-preferences.update'), [
                'comment_replies' => 'sometimes',
            ])
            ->assertSessionHasErrors(['comment_replies', 'space_moderation']);

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
