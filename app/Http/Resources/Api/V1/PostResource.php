<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Post;
use App\Models\PostMedia;
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

        return [
            'id' => (string) $post->getKey(),
            'body' => $post->body,
            'published_at' => $post->published_at?->toIso8601String(),
            'edited_at' => $post->edited_at?->toIso8601String(),
            'media' => $media instanceof PostMedia ? [
                'url' => route('api.v1.posts.media', $post),
                'alt' => $media->alt_text,
                'width' => $media->width,
                'height' => $media->height,
                'mime_type' => $media->mime_type,
            ] : null,
            'comments_count' => (int) ($post->getAttribute('comments_count') ?? 0),
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
            ],
        ];
    }
}
