<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Post;
use App\Models\PostMedia;
use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Post */
class PostResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Post $post */
        $post = $this->resource;
        $media = $post->media;
        $mediaItems = $post->mediaItems;
        /** @var array{like?: int, celebrate?: int, insightful?: int} $reactionCounts */
        $reactionCounts = $post->getAttribute('reaction_counts') ?? [];
        $reactionCounts = [
            'like' => (int) ($reactionCounts['like'] ?? 0),
            'celebrate' => (int) ($reactionCounts['celebrate'] ?? 0),
            'insightful' => (int) ($reactionCounts['insightful'] ?? 0),
        ];
        /** @var array{source: array{id: int, url: string, body: string, mediaItems: list<array{id: int, url: string, alt: string, width: int, height: int}>, publishedAt: string|null, author: array{name: string, handle: string, profileVisible: bool}, space: array{name: string, slug: string}}}|null $share */
        $share = $post->getAttribute('share');

        return [
            'id' => (string) $post->getKey(),
            'body' => $post->body,
            'mentions' => $post->getAttribute('content_mentions') ?? [],
            'topics' => $post->topics
                ->map(fn (Topic $topic): array => [
                    'name' => $topic->name,
                    'url' => route('topics.show', $topic),
                ])
                ->values()
                ->all(),
            'published_at' => $post->published_at?->toIso8601String(),
            'edited_at' => $post->edited_at?->toIso8601String(),
            'highlighted_at' => $post->highlight?->created_at->toIso8601String(),
            'share' => $share === null ? null : [
                'source' => [
                    'id' => (string) $share['source']['id'],
                    'url' => $share['source']['url'],
                    'body' => $share['source']['body'],
                    'published_at' => $share['source']['publishedAt'],
                    'author' => [
                        'handle' => $share['source']['author']['handle'],
                        'name' => $share['source']['author']['name'],
                        'profile_visible' => $share['source']['author']['profileVisible'],
                    ],
                    'space' => $share['source']['space'],
                    'media_items' => $share['source']['mediaItems'],
                ],
            ],
            'media' => $media instanceof PostMedia ? [
                'url' => route('api.v1.posts.media', $post),
                'alt' => $media->alt_text,
                'width' => $media->width,
                'height' => $media->height,
                'mime_type' => $media->mime_type,
            ] : null,
            'media_items' => $mediaItems
                ->map(fn (PostMedia $item): array => [
                    'id' => (string) $item->getKey(),
                    'url' => route('api.v1.posts.media.show', [
                        'post' => $post,
                        'media' => $item->getKey(),
                    ]),
                    'alt' => $item->alt_text,
                    'width' => $item->width,
                    'height' => $item->height,
                    'mime_type' => $item->mime_type,
                ])
                ->values()
                ->all(),
            'comments_count' => (int) ($post->getAttribute('comments_count') ?? 0),
            'reactions' => [
                'total' => array_sum($reactionCounts),
                'counts' => $reactionCounts,
            ],
            'author' => [
                'handle' => $post->author->handle,
                'name' => $post->author->name,
                'headline' => $post->author->headline,
                'profile_visible' => (bool) $post->getAttribute('author_profile_visible'),
            ],
            'space' => (new SpaceResource($post->space))->toArray($request),
            'viewer' => [
                'can_comment' => (bool) $post->getAttribute('viewer_can_comment'),
                'can_report' => (bool) $post->getAttribute('viewer_can_report'),
                'has_reported' => (bool) $post->getAttribute('viewer_has_reported'),
                'can_react' => (bool) $post->getAttribute('viewer_can_react'),
                'reaction_type' => $post->getAttribute('viewer_reaction_type'),
                'can_share' => (bool) $post->getAttribute('viewer_can_share'),
            ],
        ];
    }
}
