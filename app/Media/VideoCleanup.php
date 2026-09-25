<?php

namespace App\Media;

use App\Models\Post;
use App\Models\PostVideo;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class VideoCleanup
{
    /** @return list<string> */
    public function forPost(Post $post): array
    {
        return $this->paths($post->video()->get());
    }

    /** @return list<string> */
    public function forSpace(Space $space): array
    {
        return $this->paths(PostVideo::query()
            ->whereHas('post', fn (Builder $posts): Builder => $posts
                ->where('space_id', $space->getKey()))
            ->get());
    }

    /** @return list<string> */
    public function forUser(User $user): array
    {
        return $this->paths(PostVideo::query()
            ->whereHas('post', fn (Builder $posts): Builder => $posts
                ->where('user_id', $user->getKey())
                ->orWhereHas('space', fn (Builder $spaces): Builder => $spaces
                    ->where('owner_id', $user->getKey())))
            ->get());
    }

    /** @param list<string> $paths */
    public function afterDeletion(array $paths): void
    {
        if ($paths === []) {
            return;
        }

        DB::afterCommit(function () use ($paths): void {
            $storage = app(VideoStorage::class);

            foreach ($paths as $path) {
                try {
                    $storage->delete($path);
                } catch (RuntimeException) {
                    Log::warning('A private post video file could not be removed after deletion.');
                }
            }
        });
    }

    /** @param Collection<int, PostVideo> $videos
     * @return list<string>
     */
    private function paths(Collection $videos): array
    {
        $paths = [];

        foreach ($videos as $video) {
            foreach ([$video->source_path, $video->output_path, $video->poster_path] as $path) {
                if (! is_string($path)) {
                    continue;
                }

                if (str_starts_with($path, 'videos/source/')
                    || str_starts_with($path, "videos/ready/{$video->getKey()}/")) {
                    $paths[] = $path;
                }
            }
        }

        return array_values(array_unique($paths));
    }
}
