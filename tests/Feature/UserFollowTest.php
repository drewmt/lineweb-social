<?php

namespace Tests\Feature;

use App\Enums\ProfileVisibility;
use App\Enums\UserRelationshipType;
use App\Events\UserFollowChanged;
use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use App\Models\UserFollow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserFollowTest extends TestCase
{
    use RefreshDatabase;

    public function test_follow_and_unfollow_are_idempotent_and_emit_only_real_changes(): void
    {
        Event::fake([UserFollowChanged::class]);

        $viewer = User::factory()->create();
        $profile = User::factory()->create([
            'profile_visibility' => ProfileVisibility::Public,
        ]);

        $this->actingAs($viewer)
            ->put(route('people.follow', $profile))
            ->assertRedirect()
            ->assertSessionHas('status', "You're now following {$profile->name}.");

        $this->actingAs($viewer)
            ->put(route('people.follow', $profile))
            ->assertRedirect()
            ->assertSessionHas('status', "You're already following {$profile->name}.");

        $this->assertDatabaseCount('user_follows', 1);
        Event::assertDispatchedTimes(UserFollowChanged::class, 1);
        Event::assertDispatched(
            UserFollowChanged::class,
            fn (UserFollowChanged $event): bool => $event->follower->is($viewer)
                && $event->followed->is($profile)
                && $event->following,
        );

        $this->actingAs($viewer)
            ->delete(route('people.unfollow', $profile))
            ->assertRedirect()
            ->assertSessionHas('status', "You unfollowed {$profile->name}.");

        $this->actingAs($viewer)
            ->delete(route('people.unfollow', $profile))
            ->assertRedirect()
            ->assertSessionHas('status', "You're not following {$profile->name}.");

        $this->assertDatabaseEmpty('user_follows');
        Event::assertDispatchedTimes(UserFollowChanged::class, 2);
    }

    public function test_follow_requires_a_visible_profile_and_rejects_self_or_blocked_pairs(): void
    {
        $viewer = User::factory()->create();
        $private = User::factory()->create([
            'profile_visibility' => ProfileVisibility::Private,
        ]);
        $public = User::factory()->create([
            'profile_visibility' => ProfileVisibility::Public,
        ]);

        $this->actingAs($viewer)
            ->put(route('people.follow', $viewer))
            ->assertForbidden();
        $this->actingAs($viewer)
            ->put(route('people.follow', $private))
            ->assertForbidden();

        $public->outgoingRelationships()->create([
            'target_id' => $viewer->getKey(),
            'type' => UserRelationshipType::Block,
        ]);

        $this->actingAs($viewer)
            ->put(route('people.follow', $public))
            ->assertForbidden();

        $this->assertDatabaseEmpty('user_follows');
    }

    public function test_profile_exposes_bounded_follow_counts_and_viewer_state(): void
    {
        $viewer = User::factory()->create();
        $profile = User::factory()->create([
            'profile_visibility' => ProfileVisibility::Public,
        ]);
        $other = User::factory()->create();
        UserFollow::query()->create([
            'follower_id' => $viewer->getKey(),
            'followed_id' => $profile->getKey(),
        ]);
        UserFollow::query()->create([
            'follower_id' => $other->getKey(),
            'followed_id' => $profile->getKey(),
        ]);
        UserFollow::query()->create([
            'follower_id' => $profile->getKey(),
            'followed_id' => $other->getKey(),
        ]);

        $this->actingAs($viewer)
            ->get(route('people.show', $profile))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('profile.isFollowing', true)
                ->where('profile.canFollow', true)
                ->where('stats.followers', 2)
                ->where('stats.following', 1)
                ->missing('followers')
                ->missing('following'));
    }

    public function test_following_feed_is_chronological_and_reuses_all_visibility_boundaries(): void
    {
        $viewer = User::factory()->create();
        $followed = User::factory()->create([
            'profile_visibility' => ProfileVisibility::Public,
        ]);
        $muted = User::factory()->create([
            'profile_visibility' => ProfileVisibility::Public,
        ]);
        $unfollowed = User::factory()->create();
        $public = Space::factory()->create();
        $private = Space::factory()->private()->create();

        $older = Post::factory()->for($public)->for($followed, 'author')->create([
            'body' => 'Older followed post',
            'published_at' => now()->subMinutes(2),
        ]);
        $newer = Post::factory()->for($public)->for($followed, 'author')->create([
            'body' => 'Newer followed post',
            'published_at' => now()->subMinute(),
        ]);
        Post::factory()->for($private)->for($followed, 'author')->create([
            'body' => 'Private followed post',
        ]);
        Post::factory()->for($public)->for($muted, 'author')->create([
            'body' => 'Muted followed post',
        ]);
        Post::factory()->for($public)->for($unfollowed, 'author')->create([
            'body' => 'Unfollowed post',
        ]);

        foreach ([$followed, $muted] as $profile) {
            UserFollow::query()->create([
                'follower_id' => $viewer->getKey(),
                'followed_id' => $profile->getKey(),
            ]);
        }
        $viewer->outgoingRelationships()->create([
            'target_id' => $muted->getKey(),
            'type' => UserRelationshipType::Mute,
        ]);

        $this->actingAs($viewer)
            ->get(route('following.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('feed/index')
                ->where('viewMode', 'following')
                ->has('posts', 2)
                ->where('posts.0.id', $newer->getKey())
                ->where('posts.1.id', $older->getKey())
                ->missing('posts.2'));
    }

    public function test_blocking_removes_follows_in_both_directions(): void
    {
        Event::fake([UserFollowChanged::class]);

        $viewer = User::factory()->create();
        $profile = User::factory()->create([
            'profile_visibility' => ProfileVisibility::Public,
        ]);

        UserFollow::query()->create([
            'follower_id' => $viewer->getKey(),
            'followed_id' => $profile->getKey(),
        ]);
        UserFollow::query()->create([
            'follower_id' => $profile->getKey(),
            'followed_id' => $viewer->getKey(),
        ]);

        $this->actingAs($viewer)
            ->post(route('people.block', $profile))
            ->assertRedirect(route('people.index'));

        $this->assertDatabaseEmpty('user_follows');
        Event::assertDispatchedTimes(UserFollowChanged::class, 2);
    }

    public function test_guests_and_unverified_accounts_cannot_manage_follows(): void
    {
        $profile = User::factory()->create([
            'profile_visibility' => ProfileVisibility::Public,
        ]);

        $this->put(route('people.follow', $profile))
            ->assertRedirect(route('login'));

        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)
            ->put(route('people.follow', $profile))
            ->assertRedirect(route('verification.notice'));
    }
}
