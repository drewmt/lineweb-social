<?php

namespace App\Community;

use App\Enums\PostReactionType;
use App\Events\PostReactionChanged;
use App\Models\Post;
use App\Models\PostReaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ReactToPost
{
    /**
     * @return array{changed: bool, previousType: PostReactionType|null}
     */
    public function set(User $user, Post $post, PostReactionType $type): array
    {
        $result = DB::transaction(function () use ($user, $post, $type): array {
            $lockedPost = Post::query()
                ->with(['author', 'space'])
                ->whereKey($post->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            Gate::forUser($user)->authorize('react', $lockedPost);

            $reaction = PostReaction::query()
                ->whereBelongsTo($lockedPost)
                ->whereBelongsTo($user)
                ->lockForUpdate()
                ->first();
            $previousType = $reaction?->type;

            if ($previousType === $type) {
                return [
                    'changed' => false,
                    'previousType' => $previousType,
                    'post' => $lockedPost,
                ];
            }

            PostReaction::query()->updateOrCreate(
                [
                    'post_id' => $lockedPost->getKey(),
                    'user_id' => $user->getKey(),
                ],
                ['type' => $type],
            );

            return [
                'changed' => true,
                'previousType' => $previousType,
                'post' => $lockedPost,
            ];
        });

        if ($result['changed']) {
            PostReactionChanged::dispatch(
                $result['post'],
                $user,
                $result['previousType'],
                $type,
            );
        }

        return [
            'changed' => $result['changed'],
            'previousType' => $result['previousType'],
        ];
    }

    public function remove(User $user, int|string $postId): bool
    {
        $reaction = DB::transaction(function () use ($user, $postId): ?PostReaction {
            $candidate = PostReaction::query()
                ->where('post_id', $postId)
                ->whereBelongsTo($user)
                ->first();

            if (! $candidate instanceof PostReaction) {
                return null;
            }

            $lockedPost = Post::query()
                ->whereKey($candidate->post_id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedReaction = PostReaction::query()
                ->whereBelongsTo($lockedPost)
                ->whereBelongsTo($user)
                ->lockForUpdate()
                ->first();

            if (! $lockedReaction instanceof PostReaction) {
                return null;
            }

            $lockedReaction->setRelation('post', $lockedPost);
            $lockedReaction->delete();

            return $lockedReaction;
        });

        if (! $reaction instanceof PostReaction) {
            return false;
        }

        PostReactionChanged::dispatch(
            $reaction->post,
            $user,
            $reaction->type,
            null,
        );

        return true;
    }
}
