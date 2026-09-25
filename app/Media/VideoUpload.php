<?php

namespace App\Media;

use App\Jobs\ProcessPostVideo;
use App\Models\Post;
use App\Models\PostVideo;
use App\Models\Space;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class VideoUpload
{
    public function attach(User $author, Post $draft, UploadedFile $file, string $description): PostVideo
    {
        abort_unless(config('media.video.enabled') === true, 404);
        Gate::forUser($author)->authorize('manageDraft', $draft);
        Gate::forUser($author)->authorize('createPost', $draft->space);

        $size = $file->getSize();
        $maxBytes = min(64 * 1024 * 1024, max(1, (int) config('media.video.max_input_kilobytes', 64 * 1024)) * 1024);

        if (! is_int($size) || $size < 1 || $size > $maxBytes) {
            throw ValidationException::withMessages(['video' => 'Choose a video within the configured size limit.']);
        }

        $disk = $this->disk();
        $newPath = 'videos/source/'.Str::uuid().'.upload';
        $oldPaths = [];

        try {
            if (! Storage::disk($disk)->putFileAs('videos/source', $file, basename($newPath))) {
                throw new RuntimeException('The private video upload could not be stored.');
            }

            $video = DB::transaction(function () use (
                $author,
                $draft,
                $description,
                $size,
                $newPath,
                &$oldPaths,
            ): PostVideo {
                $lockedDraft = Post::query()
                    ->with(['mediaItems', 'poll', 'video'])
                    ->whereKey($draft->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                Gate::forUser($author)->authorize('manageDraft', $lockedDraft);
                Gate::forUser($author)->authorize('createPost', $lockedDraft->space);

                if ($lockedDraft->mediaItems->isNotEmpty() || $lockedDraft->poll !== null) {
                    throw ValidationException::withMessages([
                        'video' => 'Remove the gallery or poll before adding a video.',
                    ]);
                }

                Space::query()->whereKey($lockedDraft->space_id)->lockForUpdate()->firstOrFail();
                $oldVideo = $lockedDraft->video;
                $usage = app(VideoQuota::class)->usedBytes(
                    $lockedDraft->space_id,
                    $oldVideo?->getKey(),
                );
                $limit = min(1024 * 1024 * 1024, max(1, (int) config('media.video.space_quota_bytes', 1024 * 1024 * 1024)));

                if ($usage + $size > $limit) {
                    throw ValidationException::withMessages([
                        'video' => 'This Space has reached its video storage limit.',
                    ]);
                }

                if ($oldVideo instanceof PostVideo) {
                    $oldPaths = array_filter([
                        $oldVideo->source_path,
                        $oldVideo->output_path,
                        $oldVideo->poster_path,
                    ]);
                    $oldVideo->delete();
                }

                return $lockedDraft->video()->create([
                    'space_id' => $lockedDraft->space_id,
                    'status' => PostVideo::STATUS_PENDING,
                    'description' => trim($description),
                    'source_path' => $newPath,
                    'input_bytes' => $size,
                    'reserved_bytes' => $size,
                    'expires_at' => now()->addDays((int) config('media.video.failed_draft_retention_days', 7)),
                ]);
            });
        } catch (Throwable $exception) {
            $this->deletePaths($disk, [$newPath]);

            throw $exception;
        }

        $this->deletePaths($disk, $oldPaths);
        ProcessPostVideo::dispatch($video->getKey());

        return $video;
    }

    public function remove(User $author, Post $draft): void
    {
        $disk = $this->disk();
        $paths = DB::transaction(function () use ($author, $draft): array {
            $lockedDraft = Post::query()
                ->with('video')
                ->whereKey($draft->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            Gate::forUser($author)->authorize('manageDraft', $lockedDraft);
            $video = $lockedDraft->video;

            if (! $video instanceof PostVideo) {
                return [];
            }

            $paths = array_filter([$video->source_path, $video->output_path, $video->poster_path]);
            $video->delete();

            return $paths;
        });

        $this->deletePaths($disk, $paths);
    }

    private function disk(): string
    {
        $disk = config('media.disk');

        if (! is_string($disk) || $disk === '' || config("filesystems.disks.{$disk}.driver") !== 'local') {
            throw new RuntimeException('Video uploads require a private local media disk.');
        }

        return $disk;
    }

    /** @param array<int, string> $paths */
    private function deletePaths(string $disk, array $paths): void
    {
        foreach ($paths as $path) {
            try {
                Storage::disk($disk)->delete($path);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
