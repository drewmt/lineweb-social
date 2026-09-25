<?php

namespace Tests\Feature;

use App\Community\ReelsFeed;
use App\Models\Post;
use App\Models\PostVideo;
use App\Models\Space;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReelsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_shared_navigation_respects_the_video_feature_flag(): void
    {
        $viewer = User::factory()->create();

        config()->set('media.video.enabled', false);
        $this->actingAs($viewer)->get(route('feed'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('videoEnabled', false));

        config()->set('media.video.enabled', true);
        $this->actingAs($viewer)->get(route('feed'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('videoEnabled', true));
    }

    public function test_reels_pages_ready_visible_posts_in_stable_chronological_order(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create();
        $space = Space::factory()->for($author, 'owner')->create();
        $sameTime = now()->subMinutes(20)->startOfSecond();

        for ($i = 0; $i < 13; $i++) {
            $this->videoPost($space, $author, $i >= 10 ? $sameTime : now()->subMinutes($i));
        }

        $hidden = $this->videoPost($space, $author, now(), ['hidden_at' => now()]);
        $processing = $this->videoPost($space, $author, now());
        $processing->video()->update(['status' => PostVideo::STATUS_PROCESSING]);
        $private = Space::factory()->private()->create();
        $this->videoPost($private, $author, now());
        $blockedAuthor = User::factory()->create();
        $this->videoPost($space, $blockedAuthor, now());
        $viewer->outgoingRelationships()->create([
            'target_id' => $blockedAuthor->getKey(),
            'type' => 'block',
        ]);

        $first = app(ReelsFeed::class)->page($viewer, null);
        $second = app(ReelsFeed::class)->page($viewer, $first['nextCursor']);

        $this->assertCount(12, $first['items']);
        $this->assertCount(1, $second['items']);
        $this->assertSame([], array_intersect(
            array_column($first['items'], 'id'),
            array_column($second['items'], 'id'),
        ));
        $this->assertNotContains($hidden->getKey(), array_column($first['items'], 'id'));
        $this->assertNull($second['nextCursor']);
        $this->assertSame(
            Post::query()->where('published_at', $sameTime)->orderByDesc('id')->limit(3)->pluck('id')->all(),
            [
                ...array_slice(array_column($first['items'], 'id'), -2),
                $second['items'][0]['id'],
            ],
        );
    }

    public function test_reels_cursor_is_viewer_bound_and_page_requires_authentication(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->create();
        $space = Space::factory()->create();

        for ($i = 0; $i < 13; $i++) {
            $this->videoPost($space, $space->owner, now()->subMinutes($i));
        }

        $this->get(route('reels.index'))->assertRedirect(route('login'));
        $this->actingAs($viewer)->get(route('reels.index'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('reels/index')
            ->has('items', 12)
            ->where('items.0.video.url', route('posts.video', Post::query()->latest('published_at')->first())));

        $cursor = app(ReelsFeed::class)->page($viewer, null)['nextCursor'];
        $this->actingAs($other)->get(route('reels.index', ['cursor' => $cursor]))
            ->assertSessionHasErrors('cursor');
        $this->actingAs($viewer)->get(route('reels.index', ['cursor' => 'tampered']))
            ->assertSessionHasErrors('cursor');
        $this->actingAs($viewer)->get(route('reels.index', ['cursor' => ['invalid']]))
            ->assertSessionHasErrors('cursor');
    }

    public function test_ready_video_appears_on_profile_and_post_permalinks(): void
    {
        $author = User::factory()->create();
        $space = Space::factory()->for($author, 'owner')->create();
        $post = $this->videoPost($space, $author, now());

        $this->actingAs($author)->get(route('people.show', $author))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('posts.0.video.url', route('posts.video', $post)));
        $this->actingAs($author)->get(route('posts.show', $post))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('post.video.url', route('posts.video', $post)));
    }

    /** @param array<string, mixed> $attributes */
    private function videoPost(Space $space, User $author, CarbonInterface $publishedAt, array $attributes = []): Post
    {
        $post = Post::factory()->for($space)->for($author, 'author')->create($attributes + [
            'body' => 'Short community video',
            'published_at' => $publishedAt,
        ]);
        $post->video()->create([
            'space_id' => $space->getKey(),
            'status' => PostVideo::STATUS_READY,
            'description' => 'Synthetic short clip',
            'duration_ms' => 1000,
            'width' => 640,
            'height' => 360,
            'reserved_bytes' => 0,
        ]);

        return $post;
    }
}
