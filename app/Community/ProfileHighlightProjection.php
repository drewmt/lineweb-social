<?php

namespace App\Community;

use App\Models\ProfilePostHighlight;
use App\Models\User;

final class ProfileHighlightProjection
{
    public function __construct(
        private readonly VisiblePostQuery $visiblePosts,
    ) {}

    /** @return list<array{post_id: int, url: string, highlighted_at: string}> */
    public function referencesFor(User $profile, User $viewer): array
    {
        $highlightRows = ProfilePostHighlight::query()
            ->whereBelongsTo($profile)
            ->latest('created_at')
            ->latest('id')
            ->limit(3)
            ->get(['id', 'post_id', 'created_at']);
        $visiblePostIds = $this->visiblePosts
            ->forFeed($viewer)
            ->whereBelongsTo($profile, 'author')
            ->whereKey($highlightRows->pluck('post_id'))
            ->pluck('posts.id')
            ->all();

        return array_values(
            $highlightRows
                ->whereIn('post_id', $visiblePostIds)
                ->map(fn (ProfilePostHighlight $highlight): array => [
                    'post_id' => $highlight->post_id,
                    'url' => route('api.v1.posts.show', $highlight->post_id),
                    'highlighted_at' => $highlight->created_at->toIso8601String(),
                ])
                ->all(),
        );
    }
}
