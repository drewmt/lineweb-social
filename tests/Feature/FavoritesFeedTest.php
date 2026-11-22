<?php

namespace Tests\Feature;

use App\Enums\ProfileVisibility;
use App\Enums\UserRelationshipType;
use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FavoritesFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_favorites_are_idempotent_private_and_do_not_follow_or_join(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->create();
        $profile = User::factory()->create(['profile_visibility' => ProfileVisibility::Public]);
        $space = Space::factory()->create();

        $this->actingAs($viewer);
        foreach ([$profile, $space] as $target) {
            $url = $target instanceof User
                ? "/people/{$target->handle}/favorite"
                : "/spaces/{$target->slug}/favorite";
            $this->put($url)->assertRedirect();
            $this->put($url)->assertRedirect();
        }

        $this->get("/people/{$profile->handle}")->assertInertia(fn (Assert $page) => $page
            ->where('profile.isFavorite', true)
            ->where('profile.isFollowing', false)
            ->where('stats.followers', 0));
        $this->get("/spaces/{$space->slug}")->assertInertia(fn (Assert $page) => $page
            ->where('selectedSpaceIsFavorite', true)
            ->where('spaces.0.isMember', false));

        $this->actingAs($other)
            ->delete("/people/{$profile->handle}/favorite")->assertRedirect();
        $this->delete("/spaces/{$space->slug}/favorite")->assertRedirect();
        $this->get("/people/{$profile->handle}")->assertInertia(fn (Assert $page) => $page
            ->where('profile.isFavorite', false));
        $this->get("/spaces/{$space->slug}")->assertInertia(fn (Assert $page) => $page
            ->where('selectedSpaceIsFavorite', false));

        $this->actingAs($viewer);
        $this->get("/people/{$profile->handle}")->assertInertia(fn (Assert $page) => $page
            ->where('profile.isFavorite', true));
        $this->get("/spaces/{$space->slug}")->assertInertia(fn (Assert $page) => $page
            ->where('selectedSpaceIsFavorite', true));
        $this->delete("/people/{$profile->handle}/favorite")->assertRedirect();
        $this->delete("/spaces/{$space->slug}/favorite")->assertRedirect();
        $this->get("/people/{$profile->handle}")->assertInertia(fn (Assert $page) => $page
            ->where('profile.isFavorite', false));
        $this->get("/spaces/{$space->slug}")->assertInertia(fn (Assert $page) => $page
            ->where('selectedSpaceIsFavorite', false));
    }

    public function test_favorites_require_a_member_and_an_accessible_target(): void
    {
        $viewer = User::factory()->create();
        $privateProfile = User::factory()->create(['profile_visibility' => ProfileVisibility::Private]);
        $blockedProfile = User::factory()->create(['profile_visibility' => ProfileVisibility::Public]);
        $blockedProfile->outgoingRelationships()->create([
            'target_id' => $viewer->id,
            'type' => UserRelationshipType::Block,
        ]);
        $privateSpace = Space::factory()->private()->create();
        $hiddenSpace = Space::factory()->hidden()->create();

        $this->get('/favorites')->assertRedirect(route('login'));
        $this->put("/spaces/{$privateSpace->slug}/favorite")->assertRedirect(route('login'));

        $this->actingAs($viewer);
        foreach ([$viewer, $privateProfile, $blockedProfile] as $profile) {
            $this->put("/people/{$profile->handle}/favorite")->assertForbidden();
        }
        $this->put("/spaces/{$privateSpace->slug}/favorite")->assertForbidden();
        $this->put("/spaces/{$hiddenSpace->slug}/favorite")->assertForbidden();
        $this->get('/favorites')->assertInertia(fn (Assert $page) => $page->has('posts', 0));
    }

    public function test_favorites_merge_account_and_space_posts_once_in_chronological_order(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create(['profile_visibility' => ProfileVisibility::Public]);
        $favoriteSpace = Space::factory()->create();
        $otherSpace = Space::factory()->create();
        $fromAccount = Post::factory()->for($otherSpace)->for($author, 'author')->create([
            'published_at' => now()->subMinutes(3),
        ]);
        $fromBoth = Post::factory()->for($favoriteSpace)->for($author, 'author')->create([
            'published_at' => now()->subMinutes(2),
        ]);
        $fromSpace = Post::factory()->for($favoriteSpace)->create([
            'published_at' => now()->subMinute(),
        ]);
        Post::factory()->for($otherSpace)->create();

        $this->actingAs($viewer)->put("/people/{$author->handle}/favorite")->assertRedirect();
        $this->put("/spaces/{$favoriteSpace->slug}/favorite")->assertRedirect();
        $this->get('/favorites')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('feed/index')
            ->where('viewMode', 'favorites')
            ->has('posts', 3)
            ->where('posts.0.id', $fromSpace->id)
            ->where('posts.1.id', $fromBoth->id)
            ->where('posts.2.id', $fromAccount->id));
        $this->get('/following')->assertInertia(fn (Assert $page) => $page->has('posts', 0));
    }

    public function test_favorites_do_not_override_membership_profile_visibility_or_moderation(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create(['profile_visibility' => ProfileVisibility::Public]);
        $muted = User::factory()->create();
        $blocked = User::factory()->create();
        $private = Space::factory()->private()->create();
        $public = Space::factory()->create();
        $otherSpace = Space::factory()->create();
        $private->addMember($viewer);
        $allowed = Post::factory()->for($public)->create();
        $privatePost = Post::factory()->for($private)->create();
        $accountPost = Post::factory()->for($otherSpace)->for($author, 'author')->create();
        Post::factory()->for($public)->for($muted, 'author')->create();
        Post::factory()->for($public)->for($blocked, 'author')->create();
        Post::factory()->for($public)->create(['published_at' => null]);
        Post::factory()->for($public)->create(['hidden_at' => now()]);
        $viewer->outgoingRelationships()->create([
            'target_id' => $muted->id,
            'type' => UserRelationshipType::Mute,
        ]);
        $blocked->outgoingRelationships()->create([
            'target_id' => $viewer->id,
            'type' => UserRelationshipType::Block,
        ]);

        $this->actingAs($viewer)->put("/spaces/{$private->slug}/favorite")->assertRedirect();
        $this->put("/spaces/{$public->slug}/favorite")->assertRedirect();
        $this->put("/people/{$author->handle}/favorite")->assertRedirect();
        $this->get('/favorites')->assertInertia(fn (Assert $page) => $page
            ->has('posts', 3)
            ->where('posts.0.id', $accountPost->id)
            ->where('posts.1.id', $privatePost->id)
            ->where('posts.2.id', $allowed->id));

        $private->members()->detach($viewer);
        $author->update(['profile_visibility' => ProfileVisibility::Private]);
        $this->get('/favorites')->assertInertia(fn (Assert $page) => $page
            ->has('posts', 1)
            ->where('posts.0.id', $allowed->id));
    }

    public function test_personal_export_includes_only_the_members_own_favorite_references(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->create();
        $profile = User::factory()->create(['profile_visibility' => ProfileVisibility::Public]);
        $space = Space::factory()->create();
        $otherSpace = Space::factory()->create();

        $this->actingAs($other)->put("/spaces/{$otherSpace->slug}/favorite")->assertRedirect();
        $this->actingAs($viewer)->put("/people/{$profile->handle}/favorite")->assertRedirect();
        $this->put("/spaces/{$space->slug}/favorite")->assertRedirect();

        $response = $this->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('personal-data.export'))
            ->assertOk();
        $export = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame([$profile->id], array_column($export['favorite_profiles'], 'profile_id'));
        $this->assertSame([$space->id], array_column($export['favorite_spaces'], 'space_id'));
    }
}
