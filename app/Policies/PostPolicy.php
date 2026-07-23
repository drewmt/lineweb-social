<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function view(User $user, Post $post): bool
    {
        if ($post->published_at === null
            && $post->user_id !== $user->getKey()
            && ! $user->can('moderate', $post->space)) {
            return false;
        }

        if ($post->hidden_at !== null
            && $post->user_id !== $user->getKey()
            && ! $user->can('moderate', $post->space)) {
            return false;
        }

        return $user->can('view', $post->space)
            && ! $user->isBlockedWith($post->author);
    }

    public function report(User $user, Post $post): bool
    {
        return $post->hidden_at === null
            && $post->user_id !== $user->getKey()
            && $this->view($user, $post);
    }

    public function comment(User $user, Post $post): bool
    {
        return $post->published_at !== null
            && $post->hidden_at === null
            && $this->view($user, $post)
            && $post->space->hasMember($user);
    }

    public function save(User $user, Post $post): bool
    {
        return $post->published_at !== null
            && $post->hidden_at === null
            && $this->view($user, $post);
    }

    public function update(User $user, Post $post): bool
    {
        return $post->user_id === $user->getKey()
            && $post->published_at !== null
            && $post->hidden_at === null;
    }

    public function delete(User $user, Post $post): bool
    {
        return $this->update($user, $post);
    }
}
