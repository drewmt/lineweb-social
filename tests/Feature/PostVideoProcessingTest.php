<?php

namespace Tests\Feature;

use App\Jobs\ProcessPostVideo;
use App\Media\VideoEncoder;
use App\Media\VideoProbe;
use App\Media\VideoProbeResult;
use App\Media\VideoStorage;
use App\Models\Post;
use App\Models\PostVideo;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PostVideoProcessingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
        Queue::fake();
        config(['media.disk' => 'media', 'media.video.enabled' => true]);
    }

    public function test_upload_queues_a_dedicated_media_job(): void
    {
        [$author, $draft] = $this->draft();
        $this->upload($author, $draft, UploadedFile::fake()->create('clip.mp4', 8, 'video/mp4'));

        Queue::assertPushedOn('media', ProcessPostVideo::class);
        $this->assertSame(PostVideo::STATUS_PENDING, $draft->fresh()->video?->status);
    }

    public function test_valid_video_is_encoded_privately_and_source_is_removed(): void
    {
        $source = $this->syntheticVideo();

        try {
            [$author, $draft] = $this->draft();
            $this->upload($author, $draft, new UploadedFile($source, 'sample.mp4', 'video/mp4', null, true));
            $video = $draft->fresh()->video;
            $this->assertInstanceOf(PostVideo::class, $video);
            $sourcePath = $video->source_path;

            $this->process($video->id);

            $video->refresh();
            $this->assertSame(PostVideo::STATUS_READY, $video->status);
            $this->assertNotNull($video->duration_ms);
            $this->assertGreaterThan(0, $video->output_bytes);
            $this->assertSame(0, $video->reserved_bytes);
            Storage::disk('media')->assertExists($video->output_path);
            Storage::disk('media')->assertExists($video->poster_path);
            Storage::disk('media')->assertMissing($sourcePath);

            $outputPath = $video->output_path;
            $this->process($video->id);
            $this->assertSame($outputPath, $video->fresh()->output_path);
        } finally {
            @unlink($source);
        }
    }

    public function test_unsupported_media_fails_without_leaking_output_or_quota(): void
    {
        [$author, $draft] = $this->draft();
        $this->upload($author, $draft, UploadedFile::fake()->createWithContent('fake.mp4', 'not a video'));
        $video = $draft->fresh()->video;
        $this->assertInstanceOf(PostVideo::class, $video);
        $sourcePath = $video->source_path;

        $this->process($video->id);

        $video->refresh();
        $this->assertSame(PostVideo::STATUS_FAILED, $video->status);
        $this->assertSame(0, $video->reserved_bytes);
        $this->assertNull($video->output_path);
        Storage::disk('media')->assertMissing($sourcePath);
    }

    public function test_video_longer_than_configured_limit_is_rejected_after_probing(): void
    {
        config(['media.video.max_duration_seconds' => 1]);
        $source = $this->syntheticVideo(2);

        try {
            [$author, $draft] = $this->draft();
            $this->upload($author, $draft, new UploadedFile($source, 'long.mp4', 'video/mp4', null, true));
            $video = $draft->fresh()->video;
            $this->assertInstanceOf(PostVideo::class, $video);

            $this->process($video->id);

            $this->assertSame(PostVideo::STATUS_FAILED, $video->fresh()->status);
            $this->assertSame('invalid_duration', $video->fresh()->failure_code);
            $this->assertNull($video->fresh()->output_path);
        } finally {
            @unlink($source);
        }
    }

    public function test_replaced_video_cannot_be_resurrected_by_an_old_job(): void
    {
        [$author, $draft] = $this->draft();
        $this->upload($author, $draft, UploadedFile::fake()->create('old.mp4', 8, 'video/mp4'));
        $oldId = $draft->fresh()->video?->id;
        $this->assertNotNull($oldId);
        $this->upload($author, $draft, UploadedFile::fake()->create('new.mp4', 8, 'video/mp4'));

        $this->process($oldId);

        $this->assertDatabaseMissing('post_videos', ['id' => $oldId]);
        $this->assertSame(PostVideo::STATUS_PENDING, $draft->fresh()->video?->status);
    }

    public function test_deleted_video_during_encoding_cannot_publish_output(): void
    {
        $source = $this->syntheticVideo();

        try {
            [$author, $draft] = $this->draft();
            $this->upload($author, $draft, new UploadedFile($source, 'sample.mp4', 'video/mp4', null, true));
            $video = $draft->fresh()->video;
            $this->assertInstanceOf(PostVideo::class, $video);

            $encoder = new class($video->id) extends VideoEncoder
            {
                public function __construct(private readonly int $videoId) {}

                public function encode(string $source, string $output, string $poster, VideoProbeResult $meta): void
                {
                    parent::encode($source, $output, $poster, $meta);
                    PostVideo::query()->whereKey($this->videoId)->delete();
                }
            };

            (new ProcessPostVideo($video->id))->handle(
                app(VideoProbe::class),
                $encoder,
                app(VideoStorage::class),
            );

            $this->assertDatabaseMissing('post_videos', ['id' => $video->id]);
            $this->assertSame([], Storage::disk('media')->allFiles('videos/ready'));
        } finally {
            @unlink($source);
        }
    }

    public function test_terminal_queue_failure_releases_reservation_and_source(): void
    {
        [$author, $draft] = $this->draft();
        $this->upload($author, $draft, UploadedFile::fake()->create('clip.mp4', 8, 'video/mp4'));
        $video = $draft->fresh()->video;
        $this->assertInstanceOf(PostVideo::class, $video);
        $sourcePath = $video->source_path;

        (new ProcessPostVideo($video->id))->failed(new RuntimeException('Encoder unavailable'));

        $this->assertSame(PostVideo::STATUS_FAILED, $video->fresh()->status);
        $this->assertSame(0, $video->fresh()->reserved_bytes);
        Storage::disk('media')->assertMissing($sourcePath);
    }

    public function test_reconciliation_dry_run_preserves_and_execute_prunes_expired_failed_draft_video(): void
    {
        [, $draft] = $this->draft();
        $path = 'videos/source/00000000-0000-4000-8000-000000000001.upload';
        Storage::disk('media')->put($path, 'expired');
        $draft->video()->create([
            'space_id' => $draft->space_id,
            'status' => PostVideo::STATUS_FAILED,
            'description' => 'Expired test media',
            'source_path' => $path,
            'reserved_bytes' => 0,
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('media:videos-reconcile')->assertSuccessful();
        $this->assertDatabaseCount('post_videos', 1);
        Storage::disk('media')->assertExists($path);

        $this->artisan('media:videos-reconcile --execute')->assertSuccessful();
        $this->assertDatabaseCount('post_videos', 0);
        Storage::disk('media')->assertMissing($path);
    }

    /** @return array{User, Post} */
    private function draft(): array
    {
        $author = User::factory()->create();
        $space = Space::factory()->for($author, 'owner')->create();
        $draft = Post::factory()->for($space)->for($author, 'author')->create([
            'body' => 'A short video post',
            'published_at' => null,
        ]);

        return [$author, $draft];
    }

    private function upload(User $author, Post $draft, UploadedFile $file): void
    {
        $this->actingAs($author)->post('/drafts/'.$draft->id.'/video', [
            'video' => $file,
            'description' => 'A synthetic test video',
        ])->assertSessionHasNoErrors();
    }

    private function process(int $id): void
    {
        (new ProcessPostVideo($id))->handle(
            app(VideoProbe::class),
            app(VideoEncoder::class),
            app(VideoStorage::class),
        );
    }

    private function syntheticVideo(int $seconds = 1): string
    {
        if (Process::fromShellCommandline('command -v ffmpeg')->run() !== 0) {
            $this->markTestSkipped('FFmpeg is not installed.');
        }

        $path = tempnam(sys_get_temp_dir(), 'lineweb-video-');
        $this->assertNotFalse($path);
        $process = new Process([
            'ffmpeg', '-hide_banner', '-loglevel', 'error', '-y',
            '-f', 'lavfi', '-i', 'color=c=blue:s=320x180:r=15',
            '-t', (string) $seconds, '-pix_fmt', 'yuv420p', '-c:v', 'libx264', '-f', 'mp4', $path,
        ]);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());

        return $path;
    }
}
