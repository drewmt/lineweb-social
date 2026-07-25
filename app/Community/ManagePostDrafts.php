<?php

namespace App\Community;

use App\Community\Topics\SyncPostTopics;
use App\Events\PostPublished;
use App\Media\ImageNormalizer;
use App\Media\NormalizedImage;
use App\Models\Post;
use App\Models\PostMedia;
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

final class ManagePostDrafts
{
    public const MAX_DRAFTS_PER_MEMBER = 50;

    public function __construct(
        private readonly ImageNormalizer $images,
        private readonly SyncPostTopics $topics,
    ) {}

    public function create(
        User $author,
        Space $space,
        string $body,
        ?UploadedFile $upload,
        ?string $altText,
    ): Post {
        Gate::forUser($author)->authorize('createPost', $space);

        $normalized = $upload instanceof UploadedFile
            ? $this->images->normalize($upload)
            : null;
        $newPath = null;

        try {
            return DB::transaction(function () use (
                $author,
                $space,
                $body,
                $altText,
                $normalized,
                &$newPath,
            ): Post {
                User::query()
                    ->whereKey($author->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $draftCount = Post::query()
                    ->whereBelongsTo($author, 'author')
                    ->whereNull('published_at')
                    ->whereNull('hidden_at')
                    ->count();

                if ($draftCount >= self::MAX_DRAFTS_PER_MEMBER) {
                    throw ValidationException::withMessages([
                        'body' => 'You can keep up to 50 drafts. Publish or remove one before saving another.',
                    ]);
                }

                $draft = $space->posts()->create([
                    'user_id' => $author->getKey(),
                    'body' => trim($body),
                    'published_at' => null,
                ]);

                if ($normalized instanceof NormalizedImage) {
                    $newPath = $this->attachMedia($draft, $normalized, $altText);
                }

                return $draft->load(['space:id,name,slug,visibility', 'media']);
            });
        } catch (Throwable $exception) {
            $this->deleteFile($this->mediaDisk(), $newPath);

            throw $exception;
        }
    }

    public function update(
        User $author,
        Post $draft,
        Space $space,
        string $body,
        ?UploadedFile $upload,
        ?string $altText,
        bool $removeImage,
    ): Post {
        return $this->saveExisting(
            $author,
            $draft,
            $space,
            $body,
            $upload,
            $altText,
            $removeImage,
            publish: false,
        );
    }

    public function publish(
        User $author,
        Post $draft,
        Space $space,
        string $body,
        ?UploadedFile $upload,
        ?string $altText,
        bool $removeImage,
    ): Post {
        $post = $this->saveExisting(
            $author,
            $draft,
            $space,
            $body,
            $upload,
            $altText,
            $removeImage,
            publish: true,
        );

        PostPublished::dispatch($post);

        return $post;
    }

    public function delete(User $author, Post $draft): void
    {
        DB::transaction(function () use ($author, $draft): void {
            $lockedDraft = Post::query()
                ->whereKey($draft->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($author)->authorize('manageDraft', $lockedDraft);
            $lockedDraft->delete();
        });
    }

    private function saveExisting(
        User $author,
        Post $draft,
        Space $space,
        string $body,
        ?UploadedFile $upload,
        ?string $altText,
        bool $removeImage,
        bool $publish,
    ): Post {
        Gate::forUser($author)->authorize('createPost', $space);

        $normalized = $upload instanceof UploadedFile
            ? $this->images->normalize($upload)
            : null;
        $newPath = null;
        $obsoleteFile = null;

        try {
            $saved = DB::transaction(function () use (
                $author,
                $draft,
                $space,
                $body,
                $altText,
                $removeImage,
                $publish,
                $normalized,
                &$newPath,
                &$obsoleteFile,
            ): Post {
                $lockedDraft = Post::query()
                    ->with('media')
                    ->whereKey($draft->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                Gate::forUser($author)->authorize('manageDraft', $lockedDraft);
                Gate::forUser($author)->authorize('createPost', $space);

                $lockedDraft->update([
                    'space_id' => $space->getKey(),
                    'body' => trim($body),
                    'published_at' => $publish ? now() : null,
                    'edited_at' => null,
                ]);

                $this->syncMedia(
                    $lockedDraft,
                    $normalized,
                    $altText,
                    $removeImage,
                    $newPath,
                    $obsoleteFile,
                );

                if ($publish) {
                    $this->topics->sync($lockedDraft);
                }

                return $lockedDraft->load(['space:id,name,slug,visibility', 'media']);
            });
        } catch (Throwable $exception) {
            $this->deleteFile($this->mediaDisk(), $newPath);

            throw $exception;
        }

        if (is_array($obsoleteFile)) {
            $this->deleteFile($obsoleteFile['disk'], $obsoleteFile['path']);
        }

        return $saved;
    }

    /**
     * @param  array{disk: string, path: string}|null  $obsoleteFile
     */
    private function syncMedia(
        Post $draft,
        ?NormalizedImage $normalized,
        ?string $altText,
        bool $removeImage,
        ?string &$newPath,
        ?array &$obsoleteFile,
    ): void {
        $media = $draft->media;

        if ($normalized instanceof NormalizedImage) {
            $newPath = $this->storeNormalizedFile($normalized);
            $attributes = $this->mediaAttributes($normalized, $newPath, $altText);

            if ($media instanceof PostMedia) {
                $obsoleteFile = ['disk' => $media->disk, 'path' => $media->path];
                $media->update($attributes);
            } else {
                $draft->media()->create($attributes);
            }

            return;
        }

        if ($removeImage && $media instanceof PostMedia) {
            $obsoleteFile = ['disk' => $media->disk, 'path' => $media->path];
            DB::table('post_media')->where('id', $media->getKey())->delete();
            $draft->unsetRelation('media');

            return;
        }

        if ($media instanceof PostMedia) {
            $media->update(['alt_text' => trim((string) $altText)]);
        }
    }

    private function attachMedia(
        Post $draft,
        NormalizedImage $normalized,
        ?string $altText,
    ): string {
        $path = $this->storeNormalizedFile($normalized);
        $draft->media()->create($this->mediaAttributes($normalized, $path, $altText));

        return $path;
    }

    private function storeNormalizedFile(NormalizedImage $normalized): string
    {
        $path = 'posts/'.now()->format('Y/m').'/'.Str::uuid().'.webp';

        if (! Storage::disk($this->mediaDisk())->put($path, $normalized->contents)) {
            throw new RuntimeException('The normalized draft image could not be stored.');
        }

        return $path;
    }

    /** @return array<string, int|string> */
    private function mediaAttributes(
        NormalizedImage $normalized,
        string $path,
        ?string $altText,
    ): array {
        return [
            'disk' => $this->mediaDisk(),
            'path' => $path,
            'mime_type' => $normalized->mimeType,
            'width' => $normalized->width,
            'height' => $normalized->height,
            'size_bytes' => $normalized->sizeBytes,
            'checksum' => $normalized->checksum,
            'alt_text' => trim((string) $altText),
        ];
    }

    private function mediaDisk(): string
    {
        $disk = config('media.disk');

        if (! is_string($disk) || $disk === '') {
            throw new RuntimeException('A private media disk must be configured.');
        }

        return $disk;
    }

    private function deleteFile(string $disk, ?string $path): void
    {
        if ($path === null) {
            return;
        }

        try {
            Storage::disk($disk)->delete($path);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
