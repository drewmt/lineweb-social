<?php

namespace Tests\Feature;

use App\Enums\ProfileVisibility;
use App\Enums\UserRelationshipType;
use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use App\Models\UserRelationship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CommunitySearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_requires_a_verified_member(): void
    {
        $this->get('/search')->assertRedirect(route('login'));

        $this->actingAs(User::factory()->unverified()->create())
            ->get('/search')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_short_queries_return_an_empty_search_state(): void
    {
        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->get('/search?q=o')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('search/index')
                ->where('query', 'o')
                ->where('minimumQueryLength', 2)
                ->has('results.posts', 0)
                ->has('results.spaces', 0)
                ->has('results.people', 0));
    }

    public function test_search_returns_grouped_matches_with_safe_public_payloads(): void
    {
        $viewer = User::factory()->create();
        $person = User::factory()->create([
            'name' => 'Orchid Gardener',
            'headline' => 'Growing resilient communities',
            'profile_visibility' => ProfileVisibility::Public,
        ]);
        $space = Space::factory()->create([
            'name' => 'Orchid Builders',
            'description' => 'A practical makers circle.',
        ]);
        $post = Post::factory()->for($space)->for($person, 'author')->create([
            'body' => 'Our orchid greenhouse is open for the weekend.',
        ]);

        $this->actingAs($viewer)
            ->get('/search?q=%20orchid%20')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('search/index')
                ->where('query', 'orchid')
                ->has('results.posts', 1)
                ->where('results.posts.0.id', $post->getKey())
                ->where('results.posts.0.author.name', 'Orchid Gardener')
                ->where('results.posts.0.space.name', 'Orchid Builders')
                ->missing('results.posts.0.author.email')
                ->has('results.spaces', 1)
                ->where('results.spaces.0.slug', $space->slug)
                ->missing('results.spaces.0.owner_id')
                ->has('results.people', 1)
                ->where('results.people.0.handle', $person->handle)
                ->missing('results.people.0.email'));
    }

    public function test_search_reapplies_space_profile_and_relationship_boundaries(): void
    {
        $viewer = User::factory()->create();
        $visible = User::factory()->create([
            'name' => 'Boundary Visible',
            'profile_visibility' => ProfileVisibility::Public,
        ]);
        $muted = User::factory()->create([
            'name' => 'Boundary Muted',
            'profile_visibility' => ProfileVisibility::Public,
        ]);
        $blocked = User::factory()->create([
            'name' => 'Boundary Blocked',
            'profile_visibility' => ProfileVisibility::Public,
        ]);
        User::factory()->create([
            'name' => 'Boundary Private',
            'profile_visibility' => ProfileVisibility::Private,
        ]);
        User::factory()->create([
            'name' => 'Boundary Undiscoverable',
            'profile_visibility' => ProfileVisibility::Public,
            'is_discoverable' => false,
        ]);

        UserRelationship::query()->create([
            'actor_id' => $viewer->getKey(),
            'target_id' => $muted->getKey(),
            'type' => UserRelationshipType::Mute,
        ]);
        UserRelationship::query()->create([
            'actor_id' => $viewer->getKey(),
            'target_id' => $blocked->getKey(),
            'type' => UserRelationshipType::Block,
        ]);

        $public = Space::factory()->create(['name' => 'Boundary Public']);
        $memberOnly = Space::factory()->for($viewer, 'owner')->hidden()->create([
            'name' => 'Boundary Member Space',
        ]);
        $private = Space::factory()->private()->create(['name' => 'Boundary Private Space']);

        $visiblePost = Post::factory()->for($public)->for($visible, 'author')->create([
            'body' => 'Boundary public conversation',
        ]);
        $memberPost = Post::factory()->for($memberOnly)->for($visible, 'author')->create([
            'body' => 'Boundary member conversation',
        ]);
        Post::factory()->for($private)->for($visible, 'author')->create([
            'body' => 'Boundary inaccessible conversation',
        ]);
        Post::factory()->for($public)->for($muted, 'author')->create([
            'body' => 'Boundary muted conversation',
        ]);
        Post::factory()->for($public)->for($blocked, 'author')->create([
            'body' => 'Boundary blocked conversation',
        ]);
        Post::factory()->for($public)->for($visible, 'author')->create([
            'body' => 'Boundary hidden conversation',
            'hidden_at' => now(),
        ]);

        $this->actingAs($viewer)
            ->get('/search?q=boundary')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('results.posts', 2)
                ->where('results.posts.0.id', $memberPost->getKey())
                ->where('results.posts.1.id', $visiblePost->getKey())
                ->has('results.spaces', 2)
                ->where('results.spaces.0.name', 'Boundary Member Space')
                ->where('results.spaces.1.name', 'Boundary Public')
                ->has('results.people', 2)
                ->where('results.people.0.name', 'Boundary Muted')
                ->where('results.people.1.name', 'Boundary Visible'));
    }

    public function test_search_rejects_oversized_queries(): void
    {
        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->from('/search')
            ->get('/search?q='.str_repeat('a', 101))
            ->assertRedirect('/search')
            ->assertSessionHasErrors('q');
    }
}
