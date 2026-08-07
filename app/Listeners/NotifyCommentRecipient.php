<?php

namespace App\Listeners;

use App\Events\CommentPublished;
use App\Models\Comment;
use App\Models\NotificationPreference;
use App\Notifications\CommentReplyNotification;
use Illuminate\Support\Facades\Gate;

class NotifyCommentRecipient
{
    public function handle(CommentPublished $event): void
    {
        $comment = $event->comment->loadMissing([
            'author',
            'parent.author',
            'post.author',
            'post.space',
        ]);
        $parent = $comment->parent;

        if ($comment->parent_id !== null && ! $parent instanceof Comment) {
            return;
        }

        $recipient = $parent instanceof Comment
            ? $parent->author
            : $comment->post->author;

        if ($recipient->is($comment->author)
            || $recipient->hasMuted($comment->author)
            || $recipient->isBlockedWith($comment->author)
            || Gate::forUser($recipient)->denies('view', $comment)) {
            return;
        }

        $recipient->loadMissing('notificationPreference');

        if ($recipient->notificationPreference instanceof NotificationPreference
            && ! $recipient->notificationPreference->comment_replies) {
            return;
        }

        $recipient->notify(new CommentReplyNotification($comment));
    }
}
