<?php

namespace App\Policies;

use App\Models\Story;
use App\Models\User;

class StoryPolicy
{
    public function view(User $user, Story $story): bool
    {
        return $story->expires_at->isFuture()
            && $user->can('view', $story->space)
            && ! $user->isBlockedWith($story->author);
    }

    public function delete(User $user, Story $story): bool
    {
        return $story->user_id === $user->getKey()
            || $user->can('moderate', $story->space);
    }
}
