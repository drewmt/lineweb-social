<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Comment */
class CommentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Comment $comment */
        $comment = $this->resource;

        return [
            'id' => (string) $comment->getKey(),
            'body' => $comment->body,
            'published_at' => $comment->published_at->toIso8601String(),
            'author' => [
                'handle' => $comment->author->handle,
                'name' => $comment->author->name,
                'headline' => $comment->author->headline,
                'profile_visible' => (bool) $comment->getAttribute('author_profile_visible'),
            ],
            'viewer' => [
                'can_report' => (bool) $comment->getAttribute('viewer_can_report'),
                'has_reported' => (bool) $comment->getAttribute('viewer_has_reported'),
            ],
        ];
    }
}
