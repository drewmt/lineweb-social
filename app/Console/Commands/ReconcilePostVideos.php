<?php

namespace App\Console\Commands;

use App\Media\VideoStorage;
use App\Models\Post;
use App\Models\PostVideo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ReconcilePostVideos extends Command
{
    private const MAX_ROWS = 200;

    private const MAX_ORPHAN_FILES = 5000;

    protected $signature = 'media:videos-reconcile {--execute : Delete expired draft videos and stale private files}';

    protected $description = 'Inspect or prune expired draft video records and orphaned private source/temp files';

    public function handle(VideoStorage $videos): int
    {
        $execute = $this->option('execute') === true;
        $expired = PostVideo::query()
            ->whereHas('post', fn ($posts) => $posts->whereNull('published_at'))
            ->whereIn('status', [PostVideo::STATUS_PENDING, PostVideo::STATUS_PROCESSING, PostVideo::STATUS_FAILED])
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit(self::MAX_ROWS)
            ->pluck('id');
        $removed = 0;

        foreach ($expired as $id) {
            if (! $execute) {
                continue;
            }

            $paths = DB::transaction(function () use ($id): array {
                $initial = PostVideo::query()->whereKey($id)->first();

                if (! $initial instanceof PostVideo) {
                    return [];
                }

                $post = Post::query()->whereKey($initial->post_id)->lockForUpdate()->first();

                if (! $post instanceof Post || $post->published_at !== null) {
                    return [];
                }

                $video = PostVideo::query()->whereKey($id)->lockForUpdate()->first();

                if (! $video instanceof PostVideo
                    || $video->status === PostVideo::STATUS_READY
                    || $video->expires_at === null
                    || $video->expires_at->isFuture()) {
                    return [];
                }

                $paths = array_values(array_filter([
                    $video->source_path,
                    $video->output_path,
                    $video->poster_path,
                ], 'is_string'));
                $video->delete();

                return $paths;
            });

            foreach ($paths as $path) {
                $videos->delete($path);
            }

            $removed++;
        }

        $orphans = $this->orphanFiles($videos, $execute);
        $this->line(sprintf(
            '%s: %d expired draft records, %d stale private files%s.',
            $execute ? 'Pruned' : 'Dry run',
            $execute ? $removed : $expired->count(),
            $orphans,
            $execute ? '' : ' (use --execute to remove)',
        ));

        return self::SUCCESS;
    }

    private function orphanFiles(VideoStorage $videos, bool $execute): int
    {
        $diskName = config('media.disk');

        if (! is_string($diskName) || $diskName === '') {
            return 0;
        }

        $disk = Storage::disk($diskName);
        $count = 0;
        $seen = 0;

        foreach (['videos/source' => 7, 'videos/tmp' => 1] as $prefix => $ageDays) {
            foreach ($disk->allFiles($prefix) as $path) {
                $seen++;

                if ($seen > self::MAX_ORPHAN_FILES) {
                    return $count;
                }

                try {
                    $videos->absolute($path);

                    if ($disk->lastModified($path) > now()->subDays($ageDays)->getTimestamp()) {
                        continue;
                    }

                    if ($prefix === 'videos/source'
                        && PostVideo::query()->where('source_path', $path)->exists()) {
                        continue;
                    }

                    $count++;

                    if ($execute) {
                        $videos->delete($path);
                    }
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        }

        return $count;
    }
}
