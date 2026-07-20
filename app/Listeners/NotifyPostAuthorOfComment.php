<?php

namespace App\Listeners;

use App\Events\CommentPublished;
use App\Models\NotificationPreference;
use App\Notifications\CommentReplyNotification;
use Illuminate\Support\Facades\Gate;

class NotifyPostAuthorOfComment
{
    public function handle(CommentPublished $event): void
    {
        $comment = $event->comment->loadMissing([
            'author',
            'post.author',
            'post.space',
        ]);
        $recipient = $comment->post->author;

        if ($recipient->is($comment->author)
            || $recipient->hasMuted($comment->author)
            || $recipient->isBlockedWith($comment->author)
            || Gate::forUser($recipient)->denies('view', $comment->post)) {
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
