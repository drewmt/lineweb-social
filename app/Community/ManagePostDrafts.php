<?php

namespace App\Community;

use App\Community\Polls\PostPollDefinition;
use App\Community\Polls\SyncPostPoll;
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
        private readonly SyncPostPoll $polls,
    ) {}

    /**
     * @param  list<UploadedFile>  $uploads
     * @param  list<string>  $altTexts
     */
    public function create(
        User $author,
        Space $space,
        string $body,
        array $uploads,
        array $altTexts,
        ?PostPollDefinition $poll = null,
    ): Post {
        Gate::forUser($author)->authorize('createPost', $space);

        /** @var list<NormalizedImage> $normalized */
        $normalized = array_map(
            fn (UploadedFile $upload): NormalizedImage => $this->images->normalize($upload),
            $uploads,
        );
        /** @var list<string> $newPaths */
        $newPaths = [];

        try {
            return DB::transaction(function () use (
                $author,
                $space,
                $body,
                $altTexts,
                $normalized,
                $poll,
                &$newPaths,
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

                $this->attachGallery($draft, $normalized, $altTexts, $newPaths);
                $this->polls->replace($draft, $poll);

                return $draft->load([
                    'space:id,name,slug,visibility',
                    'media',
                    'mediaItems',
                    'poll.options',
                ]);
            });
        } catch (Throwable $exception) {
            $this->deleteFiles($this->mediaDisk(), $newPaths);

            throw $exception;
        }
    }

    /**
     * @param  list<UploadedFile>  $uploads
     * @param  list<string>  $altTexts
     * @param  array<int, string>  $retainedMediaAltTexts
     */
    public function update(
        User $author,
        Post $draft,
        Space $space,
        string $body,
        array $uploads,
        array $altTexts,
        array $retainedMediaAltTexts,
        ?PostPollDefinition $poll = null,
    ): Post {
        return $this->saveExisting(
            $author,
            $draft,
            $space,
            $body,
            $uploads,
            $altTexts,
            $retainedMediaAltTexts,
            $poll,
            publish: false,
        );
    }

    /**
     * @param  list<UploadedFile>  $uploads
     * @param  list<string>  $altTexts
     * @param  array<int, string>  $retainedMediaAltTexts
     */
    public function publish(
        User $author,
        Post $draft,
        Space $space,
        string $body,
        array $uploads,
        array $altTexts,
        array $retainedMediaAltTexts,
        ?PostPollDefinition $poll = null,
    ): Post {
        $post = $this->saveExisting(
            $author,
            $draft,
            $space,
            $body,
            $uploads,
            $altTexts,
            $retainedMediaAltTexts,
            $poll,
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

    /**
     * @param  list<UploadedFile>  $uploads
     * @param  list<string>  $altTexts
     * @param  array<int, string>  $retainedMediaAltTexts
     */
    private function saveExisting(
        User $author,
        Post $draft,
        Space $space,
        string $body,
        array $uploads,
        array $altTexts,
        array $retainedMediaAltTexts,
        ?PostPollDefinition $poll,
        bool $publish,
    ): Post {
        Gate::forUser($author)->authorize('createPost', $space);

        /** @var list<NormalizedImage> $normalized */
        $normalized = array_map(
            fn (UploadedFile $upload): NormalizedImage => $this->images->normalize($upload),
            $uploads,
        );
        /** @var list<string> $newPaths */
        $newPaths = [];
        /** @var list<array{disk: string, path: string}> $obsoleteFiles */
        $obsoleteFiles = [];

        try {
            $saved = DB::transaction(function () use (
                $author,
                $draft,
                $space,
                $body,
                $altTexts,
                $retainedMediaAltTexts,
                $publish,
                $normalized,
                $poll,
                &$newPaths,
                &$obsoleteFiles,
            ): Post {
                $lockedDraft = Post::query()
                    ->with('mediaItems')
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

                $this->syncGallery(
                    $lockedDraft,
                    $normalized,
                    $altTexts,
                    $retainedMediaAltTexts,
                    $newPaths,
                    $obsoleteFiles,
                );
                $this->polls->replace($lockedDraft, $poll);

                if ($publish) {
                    $this->topics->sync($lockedDraft);
                }

                return $lockedDraft->load([
                    'space:id,name,slug,visibility',
                    'media',
                    'mediaItems',
                    'poll.options',
                ]);
            });
        } catch (Throwable $exception) {
            $this->deleteFiles($this->mediaDisk(), $newPaths);

            throw $exception;
        }

        foreach ($obsoleteFiles as $obsoleteFile) {
            $this->deleteFile($obsoleteFile['disk'], $obsoleteFile['path']);
        }

        return $saved;
    }

    /**
     * @param  list<NormalizedImage>  $normalized
     * @param  list<string>  $altTexts
     * @param  array<int, string>  $retainedMediaAltTexts
     * @param  list<string>  $newPaths
     * @param  list<array{disk: string, path: string}>  $obsoleteFiles
     */
    private function syncGallery(
        Post $draft,
        array $normalized,
        array $altTexts,
        array $retainedMediaAltTexts,
        array &$newPaths,
        array &$obsoleteFiles,
    ): void {
        $retainedIds = array_keys($retainedMediaAltTexts);
        $retained = $draft->mediaItems
            ->filter(fn (PostMedia $media): bool => in_array($media->getKey(), $retainedIds, true))
            ->values();
        $obsolete = $draft->mediaItems
            ->reject(fn (PostMedia $media): bool => in_array($media->getKey(), $retainedIds, true))
            ->values();

        if ($retained->count() !== count($retainedIds)) {
            throw ValidationException::withMessages([
                'retained_media' => 'Retained gallery images must belong to this draft.',
            ]);
        }

        if ($retained->count() + count($normalized) > (int) config('media.max_gallery_items')) {
            throw ValidationException::withMessages([
                'images' => 'A post can contain up to four images.',
            ]);
        }

        if ($obsolete->isNotEmpty()) {
            $obsoleteFiles = array_values(
                $obsolete->map(fn (PostMedia $media): array => [
                    'disk' => $media->disk,
                    'path' => $media->path,
                ])
                    ->all(),
            );
            DB::table('post_media')->whereIn('id', $obsolete->modelKeys())->delete();
        }

        foreach ($retained as $position => $media) {
            $media->update([
                'position' => $position,
                'alt_text' => trim($retainedMediaAltTexts[$media->getKey()]),
            ]);
        }

        foreach ($normalized as $index => $image) {
            $path = $this->storeNormalizedFile($image);
            $newPaths[] = $path;
            $draft->mediaItems()->create($this->mediaAttributes(
                $image,
                $path,
                $altTexts[$index] ?? '',
                $retained->count() + $index,
            ));
        }

        $draft->unsetRelation('media');
        $draft->unsetRelation('mediaItems');
    }

    /**
     * @param  list<NormalizedImage>  $normalized
     * @param  list<string>  $altTexts
     * @param  list<string>  $newPaths
     */
    private function attachGallery(
        Post $draft,
        array $normalized,
        array $altTexts,
        array &$newPaths,
    ): void {
        foreach ($normalized as $position => $image) {
            $path = $this->storeNormalizedFile($image);
            $newPaths[] = $path;
            $draft->mediaItems()->create($this->mediaAttributes(
                $image,
                $path,
                $altTexts[$position] ?? '',
                $position,
            ));
        }
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
        int $position,
    ): array {
        return [
            'position' => $position,
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

    /** @param  list<string>  $paths */
    private function deleteFiles(string $disk, array $paths): void
    {
        foreach ($paths as $path) {
            $this->deleteFile($disk, $path);
        }
    }
}
