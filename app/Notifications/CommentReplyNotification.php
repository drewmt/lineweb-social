<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Comment;
use Illuminate\Notifications\Notification;

class CommentReplyNotification extends Notification
{
    public function __construct(private readonly Comment $comment) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return NotificationType::CommentReply->value;
    }

    /** @return array{comment_id: int, post_id: int, actor_id: int, space_id: int} */
    public function toDatabase(object $notifiable): array
    {
        return [
            'comment_id' => $this->comment->id,
            'post_id' => $this->comment->post_id,
            'actor_id' => $this->comment->user_id,
            'space_id' => $this->comment->post->space_id,
        ];
    }
}
