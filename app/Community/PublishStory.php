<?php

namespace App\Community;

use App\Media\ImageNormalizer;
use App\Media\NormalizedImage;
use App\Models\Space;
use App\Models\Story;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class PublishStory
{
    public function __construct(private readonly ImageNormalizer $images) {}

    public function publish(
        User $author,
        Space $space,
        string $body,
        string $background,
        ?UploadedFile $upload,
        string $altText,
    ): Story {
        $normalized = $upload instanceof UploadedFile
            ? $this->images->normalize($upload)
            : null;
        $disk = $normalized instanceof NormalizedImage ? $this->mediaDisk() : null;
        $path = null;

        try {
            return DB::transaction(function () use (
                $author,
                $space,
                $body,
                $background,
                $altText,
                $normalized,
                $disk,
                &$path,
            ): Story {
                $lockedSpace = Space::query()
                    ->whereKey($space->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();
                Gate::forUser($author)->authorize('createPost', $lockedSpace);

                $activeCount = Story::query()
                    ->whereBelongsTo($author, 'author')
                    ->whereBelongsTo($lockedSpace)
                    ->active()
                    ->count();

                if ($activeCount >= Story::ACTIVE_LIMIT_PER_SPACE) {
                    throw ValidationException::withMessages([
                        'space' => 'Delete an active Story or wait for one to expire before adding another here.',
                    ]);
                }

                if ($normalized instanceof NormalizedImage && is_string($disk)) {
                    $path = 'stories/'.now()->format('Y/m').'/'.Str::uuid().'.webp';

                    if (! Storage::disk($disk)->put($path, $normalized->contents)) {
                        throw new RuntimeException('The normalized Story image could not be stored.');
                    }
                }

                return Story::query()->create([
                    'space_id' => $lockedSpace->getKey(),
                    'user_id' => $author->getKey(),
                    'body' => $body === '' ? null : $body,
                    'background' => $background,
                    'disk' => $disk,
                    'path' => $path,
                    'mime_type' => $normalized?->mimeType,
                    'width' => $normalized?->width,
                    'height' => $normalized?->height,
                    'size_bytes' => $normalized?->sizeBytes,
                    'checksum' => $normalized?->checksum,
                    'alt_text' => $normalized instanceof NormalizedImage && $altText !== '' ? $altText : null,
                    'expires_at' => now()->addHours(Story::LIFETIME_HOURS),
                ]);
            });
        } catch (Throwable $exception) {
            if (is_string($disk) && is_string($path)) {
                try {
                    Storage::disk($disk)->delete($path);
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }

            throw $exception;
        }
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
