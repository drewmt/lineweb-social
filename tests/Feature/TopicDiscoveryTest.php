<?php

namespace Tests\Feature;

use App\Enums\UserRelationshipType;
use App\Models\Post;
use App\Models\Space;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TopicDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_publishing_and_editing_a_post_keep_its_topics_in_sync(): void
    {
        $author = User::factory()->create();
        $space = Space::factory()->create();
        $space->addMember($author);

        $this->actingAs($author)
            ->post(route('spaces.posts.store', $space), [
                'body' => 'Building a calmer #Laravel community with #Open_Source tools. #laravel',
            ])
            ->assertRedirect();

        $post = Post::query()->sole();

        $this->assertSame(
            ['laravel', 'open_source'],
            $post->topics()->orderBy('name')->pluck('name')->all(),
        );

        $this->actingAs($author)
            ->patch(route('posts.update', $post), [
                'body' => 'Now pairing #Laravel with #React.',
            ])
            ->assertRedirect();

        $this->assertSame(
            ['laravel', 'react'],
            $post->topics()->orderBy('name')->pluck('name')->all(),
        );

        $this->actingAs($author)
            ->get(route('feed'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('posts.0.topics', 2)
                ->where('posts.0.topics.0.name', 'laravel')
                ->where('posts.0.topics.0.url', route('topics.show', 'laravel'))
                ->where('posts.0.topics.1.name', 'react'));
    }

    public function test_topic_page_reapplies_every_current_post_visibility_boundary(): void
    {
        $viewer = User::factory()->create();
        $visibleAuthor = User::factory()->create();
        $mutedAuthor = User::factory()->create();
        $blockingAuthor = User::factory()->create();
        $topic = Topic::query()->create(['name' => 'laravel']);

        $public = Space::factory()->create();
        $memberOnly = Space::factory()->private()->create();
        $memberOnly->addMember($viewer);
        $inaccessible = Space::factory()->private()->create();

        $visiblePost = Post::factory()->for($public)->for($visibleAuthor, 'author')->create([
            'body' => 'Visible #laravel update',
            'published_at' => now()->subMinute(),
        ]);
        $memberPost = Post::factory()->for($memberOnly)->for($visibleAuthor, 'author')->create([
            'body' => 'Member #laravel update',
            'published_at' => now(),
        ]);
        $privatePost = Post::factory()->for($inaccessible)->for($visibleAuthor, 'author')->create();
        $mutedPost = Post::factory()->for($public)->for($mutedAuthor, 'author')->create();
        $blockingPost = Post::factory()->for($public)->for($blockingAuthor, 'author')->create();
        $hiddenPost = Post::factory()->for($public)->for($visibleAuthor, 'author')->create([
            'hidden_at' => now(),
        ]);
        $draftPost = Post::factory()->for($public)->for($visibleAuthor, 'author')->create([
            'published_at' => null,
        ]);
        $topic->posts()->attach([
            $visiblePost->getKey(),
            $memberPost->getKey(),
            $privatePost->getKey(),
            $mutedPost->getKey(),
            $blockingPost->getKey(),
            $hiddenPost->getKey(),
            $draftPost->getKey(),
        ]);

        $viewer->outgoingRelationships()->create([
            'target_id' => $mutedAuthor->getKey(),
            'type' => UserRelationshipType::Mute,
        ]);
        $blockingAuthor->outgoingRelationships()->create([
            'target_id' => $viewer->getKey(),
            'type' => UserRelationshipType::Block,
        ]);

        $this->actingAs($viewer)
            ->get(route('topics.show', $topic))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('feed/index')
                ->where('viewMode', 'topic')
                ->where('topic.name', 'laravel')
                ->where('topic.visiblePostCount', 2)
                ->has('posts', 2)
                ->where('posts.0.id', $memberPost->getKey())
                ->where('posts.1.id', $visiblePost->getKey()));
    }

    public function test_search_returns_only_topics_with_posts_visible_to_the_viewer(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create();
        $public = Space::factory()->create();
        $private = Space::factory()->private()->create();
        $visibleTopic = Topic::query()->create(['name' => 'laravel']);
        $privateTopic = Topic::query()->create(['name' => 'laravel-private']);
        $visiblePost = Post::factory()->for($public)->for($author, 'author')->create();
        $privatePost = Post::factory()->for($private)->for($author, 'author')->create();
        $visibleTopic->posts()->attach($visiblePost);
        $privateTopic->posts()->attach($privatePost);

        $this->actingAs($viewer)
            ->get('/search?q=%23laravel')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('results.topics', 1)
                ->where('results.topics.0.name', 'laravel')
                ->where('results.topics.0.visiblePostCount', 1)
                ->where('results.topics.0.url', route('topics.show', $visibleTopic)));

        $this->actingAs($viewer)
            ->get('/search?q=%23%23')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('results.topics', 0));
    }

    public function test_topic_discovery_requires_a_verified_member(): void
    {
        $topic = Topic::query()->create(['name' => 'laravel']);

        $this->get(route('topics.show', $topic))
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('topics.show', $topic))
            ->assertRedirect(route('verification.notice'));
    }
}
