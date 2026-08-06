<?php

namespace App\Community;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Support\Collection;

final class CommentReplyProjection
{
    public function __construct(private readonly VisibleCommentQuery $visibleComments) {}

    /**
     * Return only parent identity that the current viewer may still see.
     * Parent bodies never need to cross the public conversation contract.
     *
     * @param  Collection<int, Comment>  $comments
     * @return array<int, array{id: int, author: array{name: string, handle: string, profileVisible: bool}}>
     */
    public function for(User $viewer, Collection $comments): array
    {
        $parentIds = $comments
            ->pluck('parent_id')
            ->filter(fn (mixed $id): bool => is_int($id) || (is_string($id) && ctype_digit($id)))
            ->map(fn (int|string $id): int => (int) $id)
            ->unique()
            ->values();

        if ($parentIds->isEmpty()) {
            return [];
        }

        $parents = $this->visibleComments
            ->apply($viewer, Comment::query())
            ->whereKey($parentIds)
            ->with('author:id,name,handle')
            ->get()
            ->keyBy(fn (Comment $parent): int => $parent->getKey());
        $visibleAuthorIds = User::query()
            ->visibleTo($viewer)
            ->whereKey($parents->pluck('user_id')->unique())
            ->pluck('id')
            ->all();
        $contexts = [];

        foreach ($comments as $comment) {
            if ($comment->parent_id === null) {
                continue;
            }

            $parent = $parents->get($comment->parent_id);

            if (! $parent instanceof Comment || $parent->post_id !== $comment->post_id) {
                continue;
            }

            $contexts[$comment->getKey()] = [
                'id' => $parent->getKey(),
                'author' => [
                    'name' => $parent->author->name,
                    'handle' => $parent->author->handle,
                    'profileVisible' => in_array($parent->user_id, $visibleAuthorIds, true),
                ],
            ];
        }

        return $contexts;
    }
}
