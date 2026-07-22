<?php

namespace App\Community;

use App\Enums\UserRelationshipType;
use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class VisiblePostQuery
{
    /** @return Builder<Post> */
    public function forFeed(User $viewer, ?Space $space = null): Builder
    {
        $hiddenActorIds = $this->hiddenActorIds($viewer);
        $blockingActorIds = $this->blockingActorIds($viewer);
        $visibleComments = fn (Builder $comments): Builder => $comments
            ->whereNotNull('published_at')
            ->whereNull('hidden_at')
            ->whereNotIn('user_id', clone $hiddenActorIds)
            ->whereNotIn('user_id', clone $blockingActorIds);

        return $this->base($viewer, $space)
            ->with([
                'author:id,name,handle,headline',
                'media',
                'space' => fn ($spaces) => $spaces
                    ->addSelect([
                        'current_role' => DB::table('space_members')
                            ->select('role')
                            ->whereColumn('space_members.space_id', 'spaces.id')
                            ->where('space_members.user_id', $viewer->getKey())
                            ->limit(1),
                    ])
                    ->withExists([
                        'members as is_member' => fn ($members) => $members
                            ->whereKey($viewer->getKey()),
                    ])
                    ->withCount('members'),
            ])
            ->withCount(['comments as comments_count' => $visibleComments]);
    }

    public function findVisible(User $viewer, int|string $postId): Post
    {
        return $this->base($viewer)
            ->with('media')
            ->whereKey($postId)
            ->firstOrFail();
    }

    /** @return Builder<Post> */
    private function base(User $viewer, ?Space $space = null): Builder
    {
        $query = Post::query()
            ->whereNotNull('published_at')
            ->whereNull('hidden_at')
            ->whereNotIn('user_id', $this->hiddenActorIds($viewer))
            ->whereNotIn('user_id', $this->blockingActorIds($viewer));

        if ($space instanceof Space) {
            return $query->whereBelongsTo($space);
        }

        return $query->whereIn(
            'space_id',
            Space::query()->discoverableBy($viewer)->select('id'),
        );
    }

    private function hiddenActorIds(User $viewer): \Illuminate\Database\Query\Builder
    {
        return DB::table('user_relationships')
            ->select('target_id')
            ->where('actor_id', $viewer->getKey())
            ->whereIn('type', [
                UserRelationshipType::Mute->value,
                UserRelationshipType::Block->value,
            ]);
    }

    private function blockingActorIds(User $viewer): \Illuminate\Database\Query\Builder
    {
        return DB::table('user_relationships')
            ->select('actor_id')
            ->where('target_id', $viewer->getKey())
            ->where('type', UserRelationshipType::Block->value);
    }
}
