<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostVideo;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostVideoDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
        config(['media.disk' => 'media']);
    }

    public function test_video_supports_full_range_suffix_open_ended_head_and_invalid_ranges(): void
    {
        [$post, $author] = $this->readyPost();

        $this->actingAs($author)->get(route('posts.video', $post))
            ->assertOk()
            ->assertHeader('Content-Type', 'video/mp4')
            ->assertHeader('Content-Length', '10')
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertStreamedContent('0123456789');

        $this->actingAs($author)->withHeader('Range', 'bytes=2-5')->get(route('posts.video', $post))
            ->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 2-5/10')
            ->assertHeader('Content-Length', '4')
            ->assertStreamedContent('2345');
        $this->actingAs($author)->withHeader('Range', 'bytes=-4')->get(route('posts.video', $post))
            ->assertStatus(206)->assertStreamedContent('6789');
        $this->actingAs($author)->withHeader('Range', 'bytes=4-')->get(route('posts.video', $post))
            ->assertStatus(206)->assertStreamedContent('456789');

        $this->actingAs($author)->call('HEAD', route('posts.video', $post))
            ->assertOk()->assertHeader('Content-Length', '10')->assertDontSee('0123456789');

        foreach (['bytes=10-', 'bytes=2-1', 'bytes=0-1,3-4', 'items=0-1'] as $range) {
            $this->actingAs($author)->withHeader('Range', $range)->get(route('posts.video', $post))
                ->assertStatus(416)
                ->assertHeader('Content-Range', 'bytes */10');
        }
    }

    public function test_playback_and_poster_recheck_visibility_after_block_or_hide(): void
    {
        [$post, $author] = $this->readyPost();
        $viewer = User::factory()->create();

        $this->actingAs($viewer)->get(route('posts.video', $post))->assertOk();
        $this->actingAs($viewer)->get(route('posts.video.poster', $post))
            ->assertOk()->assertHeader('Content-Type', 'image/webp')->assertStreamedContent('poster');

        $viewer->outgoingRelationships()->create([
            'target_id' => $author->getKey(),
            'type' => 'block',
        ]);
        $this->actingAs($viewer)->get(route('posts.video', $post))->assertForbidden();
        $this->actingAs($viewer)->get(route('posts.video.poster', $post))->assertForbidden();

        $post->update(['hidden_at' => now()]);
        $this->actingAs($author)->get(route('posts.video', $post))->assertForbidden();
        $this->actingAs($author)->get(route('posts.video.poster', $post))->assertForbidden();
    }

    public function test_only_ready_private_video_is_projected_and_served(): void
    {
        [$post, $author] = $this->readyPost(true);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('posts.video', $post))->assertForbidden();
        $this->actingAs($author)->get(route('feed', ['space' => $post->space->slug]))
            ->assertOk()->assertInertia(fn ($page) => $page
            ->where('posts.0.video.url', route('posts.video', $post))
            ->where('posts.0.video.posterUrl', route('posts.video.poster', $post))
            ->missing('posts.0.video.output_path'));

        $post->video()->update(['status' => PostVideo::STATUS_PROCESSING]);
        $this->actingAs($author)->get(route('posts.video', $post))->assertNotFound();
        $this->actingAs($author)->get(route('feed', ['space' => $post->space->slug]))
            ->assertOk()->assertInertia(fn ($page) => $page->where('posts.0.video', null));
    }

    /** @return array{Post, User} */
    private function readyPost(bool $private = false): array
    {
        $author = User::factory()->create();
        $spaceFactory = Space::factory()->for($author, 'owner');
        $space = ($private ? $spaceFactory->private() : $spaceFactory)->create();
        $post = Post::factory()->for($space)->for($author, 'author')->create(['body' => 'A video post']);
        $video = $post->video()->create([
            'space_id' => $space->getKey(),
            'status' => PostVideo::STATUS_READY,
            'description' => 'A short sample',
            'output_path' => "videos/ready/{$post->getKey()}/00000000-0000-0000-0000-000000000001.mp4",
            'poster_path' => "videos/ready/{$post->getKey()}/00000000-0000-0000-0000-000000000001.webp",
            'duration_ms' => 1200,
            'width' => 640,
            'height' => 360,
            'output_bytes' => 10,
            'reserved_bytes' => 0,
        ]);
        Storage::disk('media')->put($video->output_path, '0123456789');
        Storage::disk('media')->put($video->poster_path, 'poster');

        return [$post, $author];
    }
}
