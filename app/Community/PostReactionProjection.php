<?php

namespace App\Community;

use App\Enums\PostReactionType;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class PostReactionProjection
{
    /**
     * Posts must already be filtered or authorized for the viewer.
     *
     * @param  Collection<int, Post>  $posts
     * @return array<int, array{total: int, counts: array{like: int, celebrate: int, insightful: int}, viewerType: string|null, canReact: bool}>
     */
    public function forPosts(Collection $posts, User $viewer): array
    {
        $postIds = $posts->modelKeys();
        $counts = [];

        foreach ($postIds as $postId) {
            $counts[$postId] = $this->emptyCounts();
        }

        if ($postIds !== []) {
            DB::table('post_reactions')
                ->select('post_id', 'type', DB::raw('COUNT(*) as aggregate'))
                ->whereIn('post_id', $postIds)
                ->groupBy('post_id', 'type')
                ->get()
                ->each(function (object $row) use (&$counts): void {
                    $postId = (int) $row->post_id;
                    $type = PostReactionType::tryFrom((string) $row->type);

                    if ($type instanceof PostReactionType && isset($counts[$postId])) {
                        $counts[$postId][$type->value] = (int) $row->aggregate;
                    }
                });
        }

        $viewerTypes = DB::table('post_reactions')
            ->where('user_id', $viewer->getKey())
            ->whereIn('post_id', $postIds)
            ->pluck('type', 'post_id');
        $projection = [];

        foreach ($posts as $post) {
            $postCounts = $counts[$post->getKey()] ?? $this->emptyCounts();
            $viewerType = $viewerTypes->get($post->getKey());

            $projection[$post->getKey()] = [
                'total' => array_sum($postCounts),
                'counts' => $postCounts,
                'viewerType' => is_string($viewerType) ? $viewerType : null,
                'canReact' => $post->published_at !== null
                    && $post->hidden_at === null,
            ];
        }

        return $projection;
    }

    /** @return array{like: int, celebrate: int, insightful: int} */
    private function emptyCounts(): array
    {
        return [
            PostReactionType::Like->value => 0,
            PostReactionType::Celebrate->value => 0,
            PostReactionType::Insightful->value => 0,
        ];
    }
}
