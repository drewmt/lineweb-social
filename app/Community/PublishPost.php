<?php

namespace App\Community;

use App\Community\Topics\SyncPostTopics;
use App\Events\PostPublished;
use App\Media\ImageNormalizer;
use App\Media\NormalizedImage;
use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class PublishPost
{
    public function __construct(
        private readonly ImageNormalizer $images,
        private readonly SyncPostTopics $topics,
    ) {}

    /**
     * @param  list<UploadedFile>  $uploads
     * @param  list<string>  $altTexts
     */
    public function publish(
        User $author,
        Space $space,
        string $body,
        array $uploads,
        array $altTexts,
    ): Post {
        /** @var list<NormalizedImage> $normalized */
        $normalized = array_map(
            fn (UploadedFile $upload): NormalizedImage => $this->images->normalize($upload),
            $uploads,
        );
        $disk = $this->mediaDisk();
        /** @var list<string> $paths */
        $paths = [];

        try {
            $post = DB::transaction(function () use (
                $author,
                $space,
                $body,
                $altTexts,
                $normalized,
                $disk,
                &$paths,
            ): Post {
                $post = $space->posts()->create([
                    'user_id' => $author->getKey(),
                    'body' => trim($body),
                    'published_at' => now(),
                ]);

                foreach ($normalized as $position => $image) {
                    $path = 'posts/'.now()->format('Y/m').'/'.Str::uuid().'.webp';

                    $paths[] = $path;

                    if (! Storage::disk($disk)->put($path, $image->contents)) {
                        throw new RuntimeException('The normalized post image could not be stored.');
                    }

                    $post->mediaItems()->create([
                        'position' => $position,
                        'disk' => $disk,
                        'path' => $path,
                        'mime_type' => $image->mimeType,
                        'width' => $image->width,
                        'height' => $image->height,
                        'size_bytes' => $image->sizeBytes,
                        'checksum' => $image->checksum,
                        'alt_text' => trim($altTexts[$position] ?? ''),
                    ]);
                }

                $post->load(['media', 'mediaItems']);
                $this->topics->sync($post);

                return $post;
            });
        } catch (Throwable $exception) {
            foreach ($paths as $path) {
                try {
                    Storage::disk($disk)->delete($path);
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            throw $exception;
        }

        PostPublished::dispatch($post);

        return $post;
    }

    private function mediaDisk(): string
    {
        $disk = config('media.disk');

        if (! is_string($disk) || $disk === '') {
            throw new RuntimeException('A private media disk must be configured.');
        }

        return $disk;
    }
}
