<?php

namespace App\Community;

use App\Enums\UserRelationshipType;
use App\Models\Space;
use App\Models\Story;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class StoryProjection
{
    /** @return list<array<string, mixed>> */
    public function active(User $viewer, ?Space $space = null, int $limit = 40): array
    {
        $stories = Story::query()
            ->active()
            ->when($space instanceof Space, fn (Builder $query) => $query->whereBelongsTo($space))
            ->whereIn(
                'space_id',
                Space::query()->discoverableBy($viewer)->select('spaces.id'),
            )
            ->whereDoesntHave('author.outgoingRelationships', fn (Builder $relationships) => $relationships
                ->where('target_id', $viewer->getKey())
                ->where('type', UserRelationshipType::Block))
            ->whereDoesntHave('author.incomingRelationships', fn (Builder $relationships) => $relationships
                ->where('actor_id', $viewer->getKey())
                ->where('type', UserRelationshipType::Block))
            ->with(['author:id,name,handle,profile_visibility', 'space:id,name,slug,visibility'])
            ->oldest('created_at')
            ->oldest('id')
            ->limit($limit)
            ->get();

        $visibleAuthorIds = array_values(User::query()
            ->visibleTo($viewer)
            ->whereKey($stories->pluck('user_id')->unique())
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all());

        return array_values($stories
            ->map(fn (Story $story): array => $this->for($viewer, $story, $visibleAuthorIds))
            ->all());
    }

    /** @return array<string, mixed> */
    public function one(User $viewer, Story $story): array
    {
        $story->loadMissing(['author:id,name,handle,profile_visibility', 'space:id,name,slug,visibility']);
        $visibleAuthorIds = array_values(User::query()
            ->visibleTo($viewer)
            ->whereKey($story->user_id)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all());

        return $this->for($viewer, $story, $visibleAuthorIds);
    }

    /**
     * @param  list<int>  $visibleAuthorIds
     * @return array<string, mixed>
     */
    private function for(User $viewer, Story $story, array $visibleAuthorIds): array
    {
        return [
            'id' => $story->getKey(),
            'url' => route('stories.show', $story),
            'body' => $story->body,
            'background' => $story->background,
            'image' => $story->hasImage() ? [
                'url' => route('stories.image', $story),
                'alt' => $story->alt_text ?? '',
                'width' => (int) $story->width,
                'height' => (int) $story->height,
            ] : null,
            'createdAt' => $story->created_at?->toIso8601String(),
            'expiresAt' => $story->expires_at->toIso8601String(),
            'canDelete' => $viewer->can('delete', $story),
            'ownedByViewer' => $story->user_id === $viewer->getKey(),
            'author' => [
                'name' => $story->author->name,
                'handle' => $story->author->handle,
                'profileVisible' => in_array($story->user_id, $visibleAuthorIds, true),
            ],
            'space' => [
                'name' => $story->space->name,
                'slug' => $story->space->slug,
            ],
        ];
    }
}
