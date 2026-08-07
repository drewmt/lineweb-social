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
            'mentions' => $comment->getAttribute('content_mentions') ?? [],
            'published_at' => $comment->published_at->toIso8601String(),
            'edited_at' => $comment->edited_at?->toIso8601String(),
            'is_reply' => $comment->parent_id !== null,
            'reply_to' => $this->replyTo($comment),
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

    /** @return array{id: string, author: array{name: string, handle: string, profile_visible: bool}}|null */
    private function replyTo(Comment $comment): ?array
    {
        /** @var array{id: int, author: array{name: string, handle: string, profileVisible: bool}}|null $replyTo */
        $replyTo = $comment->getAttribute('reply_to');

        if ($replyTo === null) {
            return null;
        }

        return [
            'id' => (string) $replyTo['id'],
            'author' => [
                'name' => $replyTo['author']['name'],
                'handle' => $replyTo['author']['handle'],
                'profile_visible' => $replyTo['author']['profileVisible'],
            ],
        ];
    }
}
