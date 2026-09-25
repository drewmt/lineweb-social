<?php

namespace App\Community;

use App\Models\Post;
use App\Models\PostVideo;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use JsonException;

final class ReelsFeed
{
    private const PAGE_SIZE = 12;

    public function __construct(
        private readonly VisiblePostQuery $visiblePosts,
        private readonly PostVideoView $videos,
    ) {}

    /** @return array{items: list<array<string, mixed>>, nextCursor: string|null} */
    public function page(User $viewer, ?string $cursor): array
    {
        $position = $cursor === null ? null : $this->decodeCursor($viewer, $cursor);
        $query = $this->visiblePosts->forFeed($viewer)
            ->whereHas('video', fn (Builder $videos): Builder => $videos
                ->where('status', PostVideo::STATUS_READY));

        if ($position !== null) {
            $query->where(function (Builder $posts) use ($position): void {
                $posts->where('posts.published_at', '<', $position['publishedAt'])
                    ->orWhere(function (Builder $sameTime) use ($position): void {
                        $sameTime->where('posts.published_at', $position['publishedAt'])
                            ->where('posts.id', '<', $position['id']);
                    });
            });
        }

        $rows = $query
            ->orderByDesc('posts.published_at')
            ->orderByDesc('posts.id')
            ->limit(self::PAGE_SIZE + 1)
            ->get();
        $hasMore = $rows->count() > self::PAGE_SIZE;
        $page = $rows->take(self::PAGE_SIZE);
        $last = $page->last();

        return [
            'items' => array_values($page->map(fn (Post $post): array => [
                'id' => $post->getKey(),
                'url' => route('posts.show', $post),
                'body' => $post->body,
                'publishedAt' => $post->published_at?->toIso8601String(),
                'video' => $this->videos->for($post, $viewer),
                'author' => [
                    'name' => $post->author->name,
                    'handle' => $post->author->handle,
                ],
                'space' => [
                    'name' => $post->space->name,
                    'slug' => $post->space->slug,
                ],
                'commentsCount' => (int) $post->comments_count,
                'canReport' => $viewer->can('report', $post),
            ])->all()),
            'nextCursor' => $hasMore && $last instanceof Post
                ? $this->encodeCursor($viewer, $last)
                : null,
        ];
    }

    private function encodeCursor(User $viewer, Post $post): string
    {
        return Crypt::encryptString(json_encode([
            'viewer' => $viewer->getKey(),
            'publishedAt' => $post->published_at?->format('Y-m-d H:i:s'),
            'id' => $post->getKey(),
        ], JSON_THROW_ON_ERROR));
    }

    /** @return array{publishedAt: string, id: int} */
    private function decodeCursor(User $viewer, string $cursor): array
    {
        try {
            $payload = json_decode(Crypt::decryptString($cursor), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            throw ValidationException::withMessages(['cursor' => 'The Reels cursor is invalid.']);
        }

        if (! is_array($payload)
            || ($payload['viewer'] ?? null) !== $viewer->getKey()
            || ! is_int($payload['id'] ?? null)
            || $payload['id'] < 1
            || ! is_string($payload['publishedAt'] ?? null)
            || ! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $payload['publishedAt'])) {
            throw ValidationException::withMessages(['cursor' => 'The Reels cursor is invalid.']);
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $payload['publishedAt']);
        if ($date === false || $date->format('Y-m-d H:i:s') !== $payload['publishedAt']) {
            throw ValidationException::withMessages(['cursor' => 'The Reels cursor is invalid.']);
        }

        return [
            'publishedAt' => $payload['publishedAt'],
            'id' => $payload['id'],
        ];
    }
}
