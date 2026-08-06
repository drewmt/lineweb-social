<?php

namespace App\Community;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class PublishComment
{
    public function create(User $author, Post $post, string $body, ?int $parentId = null): Comment
    {
        return DB::transaction(function () use ($author, $post, $body, $parentId): Comment {
            $lockedPost = Post::query()
                ->with(['author', 'space'])
                ->whereKey($post->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($author)->authorize('comment', $lockedPost);

            $parent = $parentId === null
                ? null
                : Comment::query()
                    ->with('author')
                    ->whereKey($parentId)
                    ->lockForUpdate()
                    ->first();

            if ($parentId !== null && ! $this->isAvailableParent($author, $lockedPost, $parent)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'The comment you are replying to is no longer available.',
                ]);
            }

            return $lockedPost->comments()->create([
                'user_id' => $author->getKey(),
                'parent_id' => $parent?->getKey(),
                'body' => $body,
                'published_at' => now(),
            ]);
        });
    }

    private function isAvailableParent(User $author, Post $post, ?Comment $parent): bool
    {
        if (! $parent instanceof Comment
            || $parent->post_id !== $post->getKey()
            || $parent->parent_id !== null
            || $parent->hidden_at !== null) {
            return false;
        }

        $parent->setRelation('post', $post);

        return Gate::forUser($author)->allows('view', $parent)
            && ! $author->hasMuted($parent->author);
    }
}
