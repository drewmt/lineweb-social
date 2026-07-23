<?php

namespace App\Http\Controllers\Api\V1;

use App\Api\V1\FeedCursor;
use App\Community\PostReactionProjection;
use App\Community\VisiblePostQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PostResource;
use App\Models\Post;
use App\Models\PostReport;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeedController extends Controller
{
    public function __invoke(
        Request $request,
        VisiblePostQuery $visiblePosts,
        FeedCursor $cursors,
        PostReactionProjection $reactions,
    ): JsonResponse {
        /** @var User $viewer */
        $viewer = $request->user();
        /** @var array{cursor?: string, limit?: int, space?: string} $validated */
        $validated = $request->validate([
            'cursor' => ['sometimes', 'string', 'max:2048'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'space' => ['sometimes', 'string', 'max:120'],
        ]);
        $limit = (int) ($validated['limit'] ?? 20);
        $space = isset($validated['space'])
            ? Space::query()
                ->discoverableBy($viewer)
                ->where('slug', $validated['space'])
                ->firstOrFail()
            : null;
        $query = $visiblePosts->forFeed($viewer, $space);

        if (isset($validated['cursor'])) {
            $cursor = $cursors->decode($validated['cursor'], $viewer, $space);

            $query->where(function (Builder $posts) use ($cursor): void {
                $posts
                    ->where('published_at', '<', $cursor['published_at'])
                    ->orWhere(function (Builder $posts) use ($cursor): void {
                        $posts
                            ->where('published_at', $cursor['published_at'])
                            ->where('id', '<', $cursor['post_id']);
                    });
            });
        }

        /** @var Collection<int, Post> $posts */
        $posts = $query
            ->latest('published_at')
            ->latest('id')
            ->limit($limit + 1)
            ->get();
        $hasMore = $posts->count() > $limit;
        $posts = $posts->take($limit)->values();

        $this->addViewerState($posts, $viewer, $reactions);

        $lastPost = $posts->last();
        $nextCursor = $hasMore && $lastPost instanceof Post
            ? $cursors->encode($viewer, $space, $lastPost)
            : null;
        $next = $nextCursor !== null
            ? route('api.v1.feed', array_filter([
                'cursor' => $nextCursor,
                'limit' => $limit,
                'space' => $space?->slug,
            ], fn (mixed $value): bool => $value !== null))
            : null;

        return response()->json([
            'data' => $posts
                ->map(fn (Post $post): array => (new PostResource($post))->toArray($request))
                ->all(),
            'links' => [
                'next' => $next,
            ],
            'meta' => [
                'next_cursor' => $nextCursor,
                'has_more' => $hasMore,
                'limit' => $limit,
            ],
        ]);
    }

    /** @param Collection<int, Post> $posts */
    private function addViewerState(
        Collection $posts,
        User $viewer,
        PostReactionProjection $reactions,
    ): void {
        $postIds = $posts->modelKeys();
        $authorIds = $posts->pluck('user_id')->unique()->values();
        $spaceIds = $posts->pluck('space_id')->unique()->values();
        $visibleAuthorIds = User::query()
            ->visibleTo($viewer)
            ->whereKey($authorIds)
            ->pluck('id')
            ->all();
        $reportedPostIds = PostReport::query()
            ->where('reporter_id', $viewer->getKey())
            ->whereIn('post_id', $postIds)
            ->pluck('post_id')
            ->all();
        $memberSpaceIds = DB::table('space_members')
            ->where('user_id', $viewer->getKey())
            ->whereIn('space_id', $spaceIds)
            ->pluck('space_id')
            ->all();
        $reactionProjection = $reactions->forPosts($posts, $viewer);

        $posts->each(function (Post $post) use (
            $viewer,
            $visibleAuthorIds,
            $reportedPostIds,
            $memberSpaceIds,
            $reactionProjection,
        ): void {
            $post->setAttribute(
                'author_profile_visible',
                in_array($post->user_id, $visibleAuthorIds, true),
            );
            $post->setAttribute(
                'viewer_can_comment',
                in_array($post->space_id, $memberSpaceIds, true),
            );
            $post->setAttribute('viewer_can_report', $post->user_id !== $viewer->getKey());
            $post->setAttribute(
                'viewer_has_reported',
                in_array($post->getKey(), $reportedPostIds, true),
            );
            $post->setAttribute(
                'reaction_counts',
                $reactionProjection[$post->getKey()]['counts'],
            );
            $post->setAttribute(
                'viewer_reaction_type',
                $reactionProjection[$post->getKey()]['viewerType'],
            );
            $post->setAttribute(
                'viewer_can_react',
                $reactionProjection[$post->getKey()]['canReact'],
            );
        });
    }
}
