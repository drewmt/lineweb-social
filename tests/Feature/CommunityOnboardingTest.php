<?php

namespace Tests\Feature;

use App\Enums\ProfileVisibility;
use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use App\Models\UserFollow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CommunityOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_verified_members_are_guided_from_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('onboarding.show'));
    }

    public function test_pending_invites_take_priority_over_onboarding(): void
    {
        $user = User::factory()->create();
        $token = str_repeat('a', 64);

        $this->actingAs($user)
            ->withSession(['pending_space_invite' => $token])
            ->get(route('dashboard'))
            ->assertRedirect(route('space-invite-links.show', ['token' => $token]));
    }

    public function test_members_with_a_space_continue_to_the_feed(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->create();
        $space->addMember($user);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('feed'));
    }

    public function test_onboarding_projects_progress_and_privacy_safe_recommendations(): void
    {
        $viewer = User::factory()->create([
            'headline' => 'Community builder',
            'bio' => 'Building useful local communities.',
        ]);
        $joinedSpace = Space::factory()->for($viewer, 'owner')->create([
            'name' => 'Joined Space',
        ]);

        $publicSpace = Space::factory()->create(['name' => 'Public Space']);
        Space::factory()->private()->create(['name' => 'Private Space']);
        Space::factory()->hidden()->create(['name' => 'Hidden Space']);

        $visiblePerson = User::factory()->create([
            'name' => 'Visible Person',
            'profile_visibility' => ProfileVisibility::Public,
        ]);
        User::factory()->create([
            'name' => 'Hidden Person',
            'profile_visibility' => ProfileVisibility::Public,
            'is_discoverable' => false,
        ]);

        UserFollow::query()->create([
            'follower_id' => $viewer->getKey(),
            'followed_id' => $visiblePerson->getKey(),
        ]);
        Post::factory()->create([
            'space_id' => $joinedSpace->getKey(),
            'user_id' => $viewer->getKey(),
            'published_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->get(route('onboarding.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('onboarding/show')
                ->where('progress.completed', 4)
                ->where('progress.total', 4)
                ->where('progress.percent', 100)
                ->where('steps.0.complete', true)
                ->where('steps.1.complete', true)
                ->where('steps.2.complete', true)
                ->where('steps.3.complete', true)
                ->has('spaces', 1)
                ->where('spaces.0.name', $publicSpace->name)
                ->has('people', 0));
    }

    public function test_onboarding_suggests_only_visible_unfollowed_people(): void
    {
        $viewer = User::factory()->create();
        $visible = User::factory()->create([
            'name' => 'Visible Person',
            'profile_visibility' => ProfileVisibility::Public,
        ]);
        $followed = User::factory()->create([
            'name' => 'Followed Person',
            'profile_visibility' => ProfileVisibility::Public,
        ]);
        User::factory()->create([
            'name' => 'Undiscoverable Person',
            'profile_visibility' => ProfileVisibility::Public,
            'is_discoverable' => false,
        ]);
        UserFollow::query()->create([
            'follower_id' => $viewer->getKey(),
            'followed_id' => $followed->getKey(),
        ]);

        $this->actingAs($viewer)
            ->get(route('onboarding.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('people', 1)
                ->where('people.0.name', $visible->name)
                ->where('people.0.handle', $visible->handle));
    }

    public function test_members_can_skip_onboarding_without_losing_direct_access(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('onboarding.dismiss'))
            ->assertRedirect(route('feed'))
            ->assertSessionHas('status', 'Getting started is available whenever you need it.');

        $this->assertNotNull($user->fresh()->onboarding_dismissed_at);

        $this->get(route('dashboard'))
            ->assertRedirect(route('feed'));

        $this->get(route('onboarding.show'))
            ->assertOk();
    }

    public function test_onboarding_requires_an_active_verified_account(): void
    {
        $this->get(route('onboarding.show'))
            ->assertRedirect(route('login'));

        $unverified = User::factory()->unverified()->create();

        $this->actingAs($unverified)
            ->get(route('onboarding.show'))
            ->assertRedirect(route('verification.notice'));
    }
}
