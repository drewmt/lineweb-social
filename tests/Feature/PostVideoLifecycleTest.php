<?php

namespace Tests\Feature;

use App\Jobs\MediaWorkerHeartbeat;
use App\Jobs\ProcessPostVideo;
use App\Media\VideoEncoder;
use App\Media\VideoProbe;
use App\Media\VideoStorage;
use App\Models\Post;
use App\Models\PostVideo;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class PostVideoLifecycleTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    protected array $connectionsToTransact = [];

    protected function setUp(): void
    {
        RefreshDatabaseState::$migrated = false;
        RefreshDatabaseState::$inMemoryConnections = [];

        parent::setUp();

        $this->withoutVite();
        Storage::fake('media');
        config(['media.disk' => 'media', 'media.video.enabled' => true]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        RefreshDatabaseState::$migrated = false;
        RefreshDatabaseState::$inMemoryConnections = [];
    }

    public function test_only_the_author_sees_draft_processing_status_and_pending_video_cannot_publish(): void
    {
        [$post, $author] = $this->videoPost(false);
        $viewer = User::factory()->create();
        $post->video()->update(['status' => PostVideo::STATUS_PENDING]);

        $this->actingAs($viewer)->get(route('drafts.edit', $post))->assertForbidden();
        $this->actingAs($author)->get(route('drafts.edit', $post))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('draft.video.status', PostVideo::STATUS_PENDING)
            ->where('draft.video.description', 'A synthetic clip')
            ->where('videoEnabled', true)
            ->missing('draft.video.output_path'));
        $this->actingAs($author)->post(route('drafts.publish', $post), [
            'space' => $post->space->slug,
            'body' => 'A video post',
        ])->assertSessionHasErrors('video');

        $post->video()->update(['status' => PostVideo::STATUS_READY]);
        $this->actingAs($author)->post(route('drafts.publish', $post), [
            'space' => $post->space->slug,
            'body' => 'A video post',
        ])->assertRedirect(route('posts.show', $post));
    }

    public function test_text_edit_preserves_ready_video_and_hiding_denies_fresh_playback(): void
    {
        [$post, $author, $video] = $this->videoPost();

        $this->actingAs($author)->patch(route('posts.update', $post), [
            'body' => 'Updated context for the video',
        ])->assertSessionHasNoErrors();
        $this->assertSame($video->getKey(), $post->fresh()->video?->getKey());
        Storage::disk('media')->assertExists($video->output_path);

        $post->update(['hidden_at' => now()]);
        $this->actingAs($author)->get(route('posts.video', $post))->assertForbidden();
        $this->actingAs($author)->get(route('posts.video.poster', $post))->assertForbidden();
    }

    public function test_author_deletion_removes_owned_video_only_after_commit(): void
    {
        [$post, $author, $video] = $this->videoPost();
        $other = $this->videoPost()[2];

        try {
            DB::transaction(function () use ($post): void {
                $post->delete();
                throw new RuntimeException('Rollback the deletion');
            });
        } catch (RuntimeException) {
            // The media must still be available after a failed transaction.
        }

        Storage::disk('media')->assertExists($video->output_path);
        $this->assertDatabaseHas('post_videos', ['id' => $video->getKey()]);

        $this->actingAs($author)->delete(route('posts.destroy', $post))->assertRedirect();
        $this->assertDatabaseMissing('post_videos', ['post_id' => $post->getKey()]);
        Storage::disk('media')->assertMissing($video->output_path);
        Storage::disk('media')->assertMissing($video->poster_path);
        Storage::disk('media')->assertExists($other->output_path);

        app(ProcessPostVideo::class, ['videoId' => $video->getKey()])->handle(
            app(VideoProbe::class), app(VideoEncoder::class), app(VideoStorage::class),
        );
        $this->assertDatabaseMissing('post_videos', ['id' => $video->getKey()]);
    }

    public function test_space_and_account_deletion_remove_cascaded_video_files(): void
    {
        [$spacePost, , $spaceVideo] = $this->videoPost();
        $spacePost->space->delete();
        Storage::disk('media')->assertMissing($spaceVideo->output_path);

        [$accountPost, $author, $accountVideo] = $this->videoPost();
        $author->delete();
        $this->assertDatabaseMissing('post_videos', ['id' => $accountVideo->getKey()]);
        Storage::disk('media')->assertMissing($accountVideo->output_path);
        $this->assertDatabaseMissing('posts', ['id' => $accountPost->getKey()]);
    }

    public function test_preflight_is_read_only_and_fails_when_media_worker_is_unavailable(): void
    {
        Queue::fake();
        config(['media.video.enabled' => false, 'queue.default' => 'sync', 'cache.default' => 'database']);
        Cache::shouldReceive('get')->once()->andThrow(new RuntimeException('Cache unavailable'));
        $before = Storage::disk('media')->allFiles();

        $this->artisan('media:video-preflight')->assertFailed();

        $this->assertFalse(config('media.video.enabled'));
        $this->assertSame($before, Storage::disk('media')->allFiles());
        Queue::assertNothingPushed();
    }

    public function test_media_worker_heartbeat_is_written_only_when_the_media_job_runs(): void
    {
        config(['cache.default' => 'array']);
        $key = MediaWorkerHeartbeat::cacheKey();
        $this->assertNull(Cache::get($key));

        (new MediaWorkerHeartbeat)->handle();

        $this->assertIsInt(Cache::get($key));
    }

    /** @return array{Post, User, PostVideo} */
    private function videoPost(bool $published = true): array
    {
        $author = User::factory()->create();
        $space = Space::factory()->for($author, 'owner')->create();
        $post = Post::factory()->for($space)->for($author, 'author')->create([
            'body' => 'A video post',
            'published_at' => $published ? now() : null,
        ]);
        $video = $post->video()->create([
            'space_id' => $space->getKey(),
            'status' => PostVideo::STATUS_READY,
            'description' => 'A synthetic clip',
            'duration_ms' => 1000,
            'width' => 640,
            'height' => 360,
            'output_bytes' => 4,
            'reserved_bytes' => 0,
        ]);
        $video->update([
            'output_path' => "videos/ready/{$video->getKey()}/00000000-0000-0000-0000-000000000001.mp4",
            'poster_path' => "videos/ready/{$video->getKey()}/00000000-0000-0000-0000-000000000001.webp",
        ]);
        Storage::disk('media')->put($video->output_path, 'clip');
        Storage::disk('media')->put($video->poster_path, 'poster');

        return [$post, $author, $video];
    }
}
