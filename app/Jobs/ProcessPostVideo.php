<?php

namespace App\Jobs;

use App\Media\VideoEncoder;
use App\Media\VideoProbe;
use App\Media\VideoProcessingException;
use App\Media\VideoQuota;
use App\Media\VideoStorage;
use App\Models\Post;
use App\Models\PostVideo;
use App\Models\Space;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ProcessPostVideo implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $videoId)
    {
        $this->timeout = min(300, max(30, (int) config('media.video.process_timeout_seconds', 180))) + 20;
        $this->onQueue((string) config('media.video.queue', 'media'));
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return (string) $this->videoId;
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('media-video-transcode'))->releaseAfter(30)->expireAfter($this->timeout + 60),
            (new WithoutOverlapping('post-video-'.$this->videoId))->releaseAfter(30)->expireAfter($this->timeout + 60),
        ];
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(VideoProbe $probe, VideoEncoder $encoder, VideoStorage $storage): void
    {
        $initial = PostVideo::query()->whereKey($this->videoId)->first();

        if (! $initial instanceof PostVideo) {
            return;
        }

        $video = DB::transaction(function () use ($initial): ?PostVideo {
            $post = Post::query()->whereKey($initial->post_id)->lockForUpdate()->first();

            if (! $post instanceof Post) {
                return null;
            }

            $current = PostVideo::query()->whereKey($this->videoId)->lockForUpdate()->first();

            if (! $current instanceof PostVideo
                || ! in_array($current->status, [PostVideo::STATUS_PENDING, PostVideo::STATUS_PROCESSING], true)
                || $current->source_path === null) {
                return null;
            }

            $current->update(['status' => PostVideo::STATUS_PROCESSING]);

            return $current;
        });

        if (! $video instanceof PostVideo) {
            return;
        }

        $paths = null;
        $ready = false;

        try {
            $source = $storage->absolute($video->source_path);

            if (! is_file($source)) {
                throw new VideoProcessingException('missing_source');
            }

            $sourceMeta = $probe->probe($source);
            $paths = $storage->paths($this->videoId);
            $encoder->encode(
                $source,
                $storage->absolute($paths['temporaryOutput']),
                $storage->absolute($paths['temporaryPoster']),
                $sourceMeta,
            );

            $outputMeta = $probe->probe($storage->absolute($paths['temporaryOutput']));
            $outputBytes = $storage->size($paths['temporaryOutput']);
            $storage->size($paths['temporaryPoster']);

            if ($outputBytes > 64 * 1024 * 1024
                || $outputMeta->durationMs > $sourceMeta->durationMs + 1000
                || $outputMeta->width > 1280
                || $outputMeta->height > 720) {
                throw new VideoProcessingException('processing_failed');
            }

            $checksum = $storage->checksum($paths['temporaryOutput']);
            $storage->promote($paths['temporaryOutput'], $paths['output']);
            $storage->promote($paths['temporaryPoster'], $paths['poster']);

            $ready = DB::transaction(function () use ($video, $paths, $outputMeta, $outputBytes, $checksum): bool {
                $post = Post::query()->whereKey($video->post_id)->lockForUpdate()->first();

                if (! $post instanceof Post) {
                    return false;
                }

                Space::query()->whereKey($post->space_id)->lockForUpdate()->firstOrFail();
                $current = PostVideo::query()->whereKey($this->videoId)->lockForUpdate()->first();

                if (! $current instanceof PostVideo
                    || $current->status !== PostVideo::STATUS_PROCESSING
                    || $current->source_path !== $video->source_path
                    || $current->post_id !== $post->getKey()
                    || $current->space_id !== $post->space_id) {
                    return false;
                }

                $usage = app(VideoQuota::class)->usedBytes($post->space_id, $current->getKey());
                $limit = min(1024 * 1024 * 1024, max(1, (int) config('media.video.space_quota_bytes', 1024 * 1024 * 1024)));

                if ($usage + $outputBytes > $limit) {
                    throw new VideoProcessingException('quota_exceeded');
                }

                $current->update([
                    'status' => PostVideo::STATUS_READY,
                    'source_path' => null,
                    'output_path' => $paths['output'],
                    'poster_path' => $paths['poster'],
                    'duration_ms' => $outputMeta->durationMs,
                    'width' => $outputMeta->width,
                    'height' => $outputMeta->height,
                    'output_bytes' => $outputBytes,
                    'reserved_bytes' => 0,
                    'checksum' => $checksum,
                    'failure_code' => null,
                    'expires_at' => null,
                ]);

                return true;
            });

            if ($ready) {
                try {
                    $storage->delete($video->source_path);
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        } catch (VideoProcessingException $exception) {
            $this->markFailed($exception->failureCode, $storage);
        } finally {
            if (is_array($paths)) {
                $storage->delete($paths['temporaryOutput']);
                $storage->delete($paths['temporaryPoster']);

                if (! $ready) {
                    $storage->delete($paths['output']);
                    $storage->delete($paths['poster']);
                }
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->markFailed('processing_failed', app(VideoStorage::class));
    }

    private function markFailed(string $code, VideoStorage $storage): void
    {
        $source = DB::transaction(function () use ($code): ?string {
            $initial = PostVideo::query()->whereKey($this->videoId)->first();

            if (! $initial instanceof PostVideo) {
                return null;
            }

            $post = Post::query()->whereKey($initial->post_id)->lockForUpdate()->first();

            if (! $post instanceof Post) {
                return null;
            }

            $video = PostVideo::query()->whereKey($this->videoId)->lockForUpdate()->first();

            if (! $video instanceof PostVideo || $video->status === PostVideo::STATUS_READY) {
                return null;
            }

            $path = $video->source_path;
            $video->update([
                'status' => PostVideo::STATUS_FAILED,
                'source_path' => null,
                'reserved_bytes' => 0,
                'failure_code' => $code,
                'expires_at' => now()->addDays((int) config('media.video.failed_draft_retention_days', 7)),
            ]);

            return $path;
        });

        if ($source !== null) {
            try {
                $storage->delete($source);
            } catch (RuntimeException $exception) {
                report($exception);
            }
        }
    }
}
