<?php

namespace App\Community;

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
    public function __construct(private readonly ImageNormalizer $images) {}

    public function publish(
        User $author,
        Space $space,
        string $body,
        ?UploadedFile $upload,
        ?string $altText,
    ): Post {
        $normalized = $upload instanceof UploadedFile
            ? $this->images->normalize($upload)
            : null;
        $disk = $this->mediaDisk();
        $path = null;

        try {
            $post = DB::transaction(function () use (
                $author,
                $space,
                $body,
                $altText,
                $normalized,
                $disk,
                &$path,
            ): Post {
                $post = $space->posts()->create([
                    'user_id' => $author->getKey(),
                    'body' => trim($body),
                    'published_at' => now(),
                ]);

                if ($normalized instanceof NormalizedImage) {
                    $path = 'posts/'.now()->format('Y/m').'/'.Str::uuid().'.webp';

                    if (! Storage::disk($disk)->put($path, $normalized->contents)) {
                        throw new RuntimeException('The normalized post image could not be stored.');
                    }

                    $media = $post->media()->create([
                        'disk' => $disk,
                        'path' => $path,
                        'mime_type' => $normalized->mimeType,
                        'width' => $normalized->width,
                        'height' => $normalized->height,
                        'size_bytes' => $normalized->sizeBytes,
                        'checksum' => $normalized->checksum,
                        'alt_text' => trim((string) $altText),
                    ]);
                    $post->setRelation('media', $media);
                }

                return $post;
            });
        } catch (Throwable $exception) {
            if (is_string($path)) {
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
