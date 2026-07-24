<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Notifications\Notification;

class ContentMentionNotification extends Notification
{
    private function __construct(
        private readonly string $contentType,
        private readonly int $contentId,
        private readonly int $postId,
        private readonly int $actorId,
    ) {}

    public static function forPost(Post $post): self
    {
        return new self(
            'post',
            $post->getKey(),
            $post->getKey(),
            $post->user_id,
        );
    }

    public static function forComment(Comment $comment): self
    {
        return new self(
            'comment',
            $comment->getKey(),
            $comment->post_id,
            $comment->user_id,
        );
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return NotificationType::ContentMention->value;
    }

    /** @return array{actor_id: int, content_id: int, content_type: string, post_id: int} */
    public function toDatabase(object $notifiable): array
    {
        return [
            'actor_id' => $this->actorId,
            'content_id' => $this->contentId,
            'content_type' => $this->contentType,
            'post_id' => $this->postId,
        ];
    }
}
