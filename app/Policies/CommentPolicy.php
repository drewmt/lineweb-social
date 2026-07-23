<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    public function view(User $user, Comment $comment): bool
    {
        if ($comment->hidden_at !== null
            && $comment->user_id !== $user->getKey()
            && ! $user->can('moderate', $comment->post->space)) {
            return false;
        }

        return $user->can('view', $comment->post)
            && ! $user->isBlockedWith($comment->author);
    }

    public function report(User $user, Comment $comment): bool
    {
        return $comment->hidden_at === null
            && $comment->post->hidden_at === null
            && $comment->user_id !== $user->getKey()
            && $this->view($user, $comment);
    }

    public function update(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->getKey()
            && $comment->hidden_at === null
            && $comment->post->hidden_at === null;
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $this->update($user, $comment);
    }
}
