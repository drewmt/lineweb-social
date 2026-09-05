<?php

namespace Tests\Feature;

use App\Enums\ProfileVisibility;
use App\Enums\UserRelationshipType;
use App\Models\Post;
use App\Models\Space;
use App\Models\Topic;
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

    public function test_category_search_reaches_matches_beyond_the_overview(): void
    {
        $viewer = User::factory()->create();
        $space = Space::factory()->create(['name' => 'Orchid circle']);
        $posts = Post::factory()->count(11)->for($space)->create([
            'body' => 'Orchid community conversation',
            'published_at' => now(),
        ])->sortByDesc('id')->values();

        $this->actingAs($viewer)->get('/search?q=orchid')
            ->assertInertia(fn (Assert $page) => $page
                ->where('type', 'all')
                ->where('pagination', null)
                ->has('results.posts', 8));

        $this->get('/search?q=orchid&type=posts')
            ->assertInertia(fn (Assert $page) => $page
                ->where('type', 'posts')
                ->has('results.posts', 8)
                ->where('results.posts.0.id', $posts[0]->id)
                ->has('results.spaces', 0)
                ->has('results.people', 0)
                ->has('results.topics', 0)
                ->where('pagination.currentPage', 1)
                ->where('pagination.previousUrl', null)
                ->where('pagination.nextUrl', route('search', ['q' => 'orchid', 'type' => 'posts', 'page' => 2])));

        $this->get('/search?q=orchid&type=posts&page=2')
            ->assertInertia(fn (Assert $page) => $page
                ->has('results.posts', 3)
                ->where('results.posts.0.id', $posts[8]->id)
                ->where('pagination.currentPage', 2)
                ->where('pagination.nextUrl', null)
                ->where('pagination.previousUrl', route('search', ['q' => 'orchid', 'type' => 'posts', 'page' => 1])));
    }

    public function test_category_pages_use_deterministic_space_and_people_order(): void
    {
        $viewer = User::factory()->create();
        $spaces = Space::factory()->count(9)->create(['name' => 'Orchid circle']);
        $people = User::factory()->count(9)->create([
            'name' => 'Orchid member',
            'profile_visibility' => ProfileVisibility::Public,
        ]);

        foreach (['spaces' => $spaces[8]->slug, 'people' => $people[8]->handle] as $type => $identifier) {
            $field = $type === 'spaces' ? 'slug' : 'handle';

            $this->actingAs($viewer)->get('/search?q=orchid&type='.$type.'&page=2')
                ->assertInertia(fn (Assert $page) => $page
                    ->has('results.'.$type, 1)
                    ->where('results.'.$type.'.0.'.$field, $identifier)
                    ->has('results.posts', 0)
                    ->has('results.topics', 0)
                    ->where('pagination.nextUrl', null));
        }
    }

    public function test_focused_topic_search_counts_only_currently_visible_posts(): void
    {
        $viewer = User::factory()->create();
        $visible = Post::factory()->create();
        $private = Post::factory()->for(Space::factory()->private())->create();

        foreach (range(1, 9) as $number) {
            $topic = Topic::query()->create(['name' => 'orchid'.$number]);
            $visible->topics()->attach($topic);
            $private->topics()->attach($topic);
        }

        $privateTopic = Topic::query()->create(['name' => 'orchid-secret']);
        $private->topics()->attach($privateTopic);

        $this->actingAs($viewer)->get('/search?q=%23orchid&type=topics&page=2')
            ->assertInertia(fn (Assert $page) => $page
                ->has('results.topics', 1)
                ->where('results.topics.0.name', 'orchid9')
                ->where('results.topics.0.visiblePostCount', 1)
                ->has('results.posts', 0)
                ->has('results.spaces', 0)
                ->has('results.people', 0)
                ->where('pagination.nextUrl', null));
    }

    public function test_focused_pages_reapply_visibility_before_pagination(): void
    {
        $viewer = User::factory()->create();
        $space = Space::factory()->create();
        $author = User::factory()->create();
        Post::factory()->count(9)->for($space)->for($author, 'author')->create(['body' => 'Orchid visible']);
        Post::factory()->count(9)->create(['body' => 'Orchid draft', 'published_at' => null]);
        Post::factory()->count(9)->create(['body' => 'Orchid hidden', 'hidden_at' => now()]);
        Post::factory()->count(9)->for(Space::factory()->private())->create(['body' => 'Orchid private']);

        $this->actingAs($viewer)->get('/search?q=orchid&type=posts&page=2')
            ->assertInertia(fn (Assert $page) => $page
                ->has('results.posts', 1)
                ->where('results.posts.0.body', 'Orchid visible')
                ->where('pagination.nextUrl', null));

        UserRelationship::query()->create([
            'actor_id' => $author->id,
            'target_id' => $viewer->id,
            'type' => UserRelationshipType::Block,
        ]);

        $this->get('/search?q=orchid&type=posts&page=2')
            ->assertInertia(fn (Assert $page) => $page
                ->has('results.posts', 0)
                ->where('pagination.nextUrl', null)
                ->where('pagination.currentPage', 2));
    }

    public function test_search_rejects_invalid_filters_and_page_numbers(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['type' => ['members', ['posts']], 'page' => [0, -1, '2.5', 1001, ['2']]] as $field => $values) {
            foreach ($values as $value) {
                $this->getJson('/search?'.http_build_query(['q' => 'orchid', $field => $value]))
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors($field);
            }
        }
    }

    public function test_short_focused_queries_do_not_offer_empty_pagination(): void
    {
        $this->actingAs(User::factory()->create())->get('/search?q=o&type=posts&page=2')
            ->assertInertia(fn (Assert $page) => $page
                ->where('type', 'posts')
                ->where('pagination', null)
                ->has('results.posts', 0));
    }

    public function test_overview_ignores_page_and_pagination_preserves_normalized_queries(): void
    {
        $viewer = User::factory()->create();
        Post::factory()->count(9)->create(['body' => 'Orchid design']);

        $this->actingAs($viewer)->get('/search?q=orchid&page=2')
            ->assertInertia(fn (Assert $page) => $page
                ->has('results.posts', 8)
                ->where('pagination', null));

        $this->get('/search?'.http_build_query(['q' => '  Orchid   design ', 'type' => 'posts']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('query', 'Orchid design')
                ->where('pagination.nextUrl', route('search', ['q' => 'Orchid design', 'type' => 'posts', 'page' => 2])));
    }
}
