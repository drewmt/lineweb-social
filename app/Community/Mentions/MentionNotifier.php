<?php

namespace App\Community\Mentions;

use App\Models\Comment;
use App\Models\NotificationPreference;
use App\Models\Post;
use App\Models\User;
use App\Notifications\ContentMentionNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

final class MentionNotifier
{
    public function __construct(private readonly MentionParser $parser) {}

    /** @param list<string> $previousHandles */
    public function forPost(Post $post, array $previousHandles = []): void
    {
        $post->loadMissing(['author', 'space']);
        $handles = $this->newHandles($post->body, $previousHandles);
        $recipients = $this->recipients($post->author, $handles)
            ->filter(fn (User $recipient): bool => Gate::forUser($recipient)->allows('view', $post));

        Notification::send(
            $recipients,
            ContentMentionNotification::forPost($post),
        );
    }

    /** @param list<string> $previousHandles */
    public function forComment(Comment $comment, array $previousHandles = []): void
    {
        $comment->loadMissing(['author', 'post.author', 'post.space']);
        $handles = $this->newHandles($comment->body, $previousHandles);
        $recipients = $this->recipients($comment->author, $handles)
            ->filter(function (User $recipient) use ($comment): bool {
                if (Gate::forUser($recipient)->denies('view', $comment)) {
                    return false;
                }

                if (! $recipient->is($comment->post->author)) {
                    return true;
                }

                $recipient->loadMissing('notificationPreference');

                return $recipient->notificationPreference instanceof NotificationPreference
                    && ! $recipient->notificationPreference->comment_replies;
            });

        Notification::send(
            $recipients,
            ContentMentionNotification::forComment($comment),
        );
    }

    /**
     * @param  list<string>  $handles
     * @return Collection<int, User>
     */
    private function recipients(User $author, array $handles): Collection
    {
        if ($handles === []) {
            return new Collection;
        }

        return User::query()
            ->visibleTo($author)
            ->whereKeyNot($author->getKey())
            ->whereIn('handle', $handles)
            ->with('notificationPreference')
            ->get()
            ->filter(fn (User $recipient): bool => ! $recipient->hasMuted($author)
                && (! $recipient->notificationPreference instanceof NotificationPreference
                    || $recipient->notificationPreference->content_mentions));
    }

    /**
     * @param  list<string>  $previousHandles
     * @return list<string>
     */
    private function newHandles(string $body, array $previousHandles): array
    {
        return array_values(array_diff(
            $this->parser->handles($body),
            $previousHandles,
        ));
    }
}
