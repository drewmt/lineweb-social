<?php

namespace Tests\Feature\Settings;

use App\Enums\ProfileVisibility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), $this->profilePayload($user, [
                'name' => 'Test User',
                'handle' => 'test-user',
                'email' => 'test@example.com',
                'headline' => 'Community product builder',
                'bio' => 'Building thoughtful communities.',
                'location' => 'Thessaloniki',
                'website_url' => 'https://lineweb.gr',
                'profile_visibility' => ProfileVisibility::Public->value,
                'is_discoverable' => false,
            ]));

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertSame('test-user', $user->handle);
        $this->assertSame('Community product builder', $user->headline);
        $this->assertSame('Building thoughtful communities.', $user->bio);
        $this->assertSame('Thessaloniki', $user->location);
        $this->assertSame('https://lineweb.gr', $user->website_url);
        $this->assertSame(ProfileVisibility::Public, $user->profile_visibility);
        $this->assertFalse($user->is_discoverable);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), $this->profilePayload($user, [
                'name' => 'Test User',
                'email' => $user->email,
            ]));

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_profile_handle_and_privacy_settings_are_validated(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create(['handle' => 'already-taken']);

        $this->actingAs($user)
            ->patch(route('profile.update'), $this->profilePayload($user, [
                'handle' => 'Already Taken',
                'headline' => str_repeat('a', 121),
                'website_url' => 'javascript:alert(1)',
                'profile_visibility' => 'friends',
                'is_discoverable' => 'sometimes',
            ]))
            ->assertSessionHasErrors([
                'handle',
                'headline',
                'website_url',
                'profile_visibility',
                'is_discoverable',
            ]);

        $this->assertSame('already-taken', $other->handle);
        $this->assertNotSame('already-taken', $user->refresh()->handle);
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }

    /** @param  array<string, mixed>  $overrides */
    private function profilePayload(User $user, array $overrides = []): array
    {
        return [
            'name' => $user->name,
            'handle' => $user->handle,
            'email' => $user->email,
            'headline' => $user->headline,
            'bio' => $user->bio,
            'location' => $user->location,
            'website_url' => $user->website_url,
            'profile_visibility' => $user->profile_visibility->value,
            'is_discoverable' => $user->is_discoverable,
            ...$overrides,
        ];
    }
}
