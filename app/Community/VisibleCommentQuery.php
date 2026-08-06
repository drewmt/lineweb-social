<?php

namespace App\Community;

use App\Enums\UserRelationshipType;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class VisibleCommentQuery
{
    /** @return Builder<Comment> */
    public function forPost(User $viewer, Post $post): Builder
    {
        return $this->apply($viewer, Comment::query())
            ->whereBelongsTo($post);
    }

    /**
     * @param  Builder<Comment>  $comments
     * @return Builder<Comment>
     */
    public function apply(User $viewer, Builder $comments): Builder
    {
        $hiddenActorIds = DB::table('user_relationships')
            ->select('target_id')
            ->where('actor_id', $viewer->getKey())
            ->whereIn('type', [
                UserRelationshipType::Mute->value,
                UserRelationshipType::Block->value,
            ]);
        $blockingActorIds = DB::table('user_relationships')
            ->select('actor_id')
            ->where('target_id', $viewer->getKey())
            ->where('type', UserRelationshipType::Block->value);

        return $comments
            ->whereNotNull('published_at')
            ->whereNull('hidden_at')
            ->whereNotIn('user_id', $hiddenActorIds)
            ->whereNotIn('user_id', $blockingActorIds);
    }
}
