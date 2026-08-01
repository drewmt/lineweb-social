<?php

namespace App\Community;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class PostShareProjection
{
    public function __construct(private readonly PostMediaView $media) {}

    /**
     * @param  Collection<int, Post>  $posts
     * @return array<int, array{source: array{id: int, url: string, body: string, mediaItems: list<array{id: int, url: string, alt: string, width: int, height: int}>, publishedAt: string|null, author: array{name: string, handle: string, profileVisible: bool}, space: array{name: string, slug: string}}}|null>
     */
    public function forPosts(Collection $posts, User $viewer): array
    {
        $sharedPosts = $posts
            ->mapWithKeys(function (Post $post): array {
                $source = $post->sharedPost;

                return $source instanceof Post
                    ? [$post->getKey() => $source]
                    : [];
            });

        if ($sharedPosts->isEmpty()) {
            return [];
        }

        $visibleAuthorIds = User::query()
            ->visibleTo($viewer)
            ->whereKey($sharedPosts->pluck('user_id')->unique())
            ->pluck('id')
            ->all();

        return $posts
            ->mapWithKeys(function (Post $post) use ($viewer, $visibleAuthorIds): array {
                $source = $post->sharedPost;

                if (! $source instanceof Post || ! $viewer->can('view', $source)) {
                    return [$post->getKey() => null];
                }

                return [$post->getKey() => [
                    'source' => [
                        'id' => $source->getKey(),
                        'url' => route('posts.show', $source),
                        'body' => $source->body,
                        'mediaItems' => $this->media->galleryFor($source),
                        'publishedAt' => $source->published_at?->toIso8601String(),
                        'author' => [
                            'name' => $source->author->name,
                            'handle' => $source->author->handle,
                            'profileVisible' => in_array($source->user_id, $visibleAuthorIds, true),
                        ],
                        'space' => [
                            'name' => $source->space->name,
                            'slug' => $source->space->slug,
                        ],
                    ],
                ]];
            })
            ->all();
    }
}
