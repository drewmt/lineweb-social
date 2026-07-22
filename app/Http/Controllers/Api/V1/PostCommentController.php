<?php

namespace App\Http\Controllers\Api\V1;

use App\Api\V1\PostCommentCursor;
use App\Community\VisiblePostQuery;
use App\Enums\UserRelationshipType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CommentResource;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PostCommentController extends Controller
{
    public function __invoke(
        Request $request,
        string $post,
        VisiblePostQuery $visiblePosts,
        PostCommentCursor $cursorFactory,
    ): JsonResponse {
        /** @var User $viewer */
        $viewer = $request->user();
        /** @var array{cursor?: string, limit?: int} $validated */
        $validated = $request->validate([
            'cursor' => ['sometimes', 'string', 'max:2048'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $postModel = $visiblePosts->forFeed($viewer)
            ->whereKey($post)
            ->firstOrFail();
        $limit = (int) ($validated['limit'] ?? 20);

        $query = $this->visibleComments($viewer, $postModel);

        if (isset($validated['cursor'])) {
            $cursor = $cursorFactory->decode($validated['cursor'], $viewer, $postModel);

            $query->where(function (Builder $comments) use ($cursor): void {
                $comments
                    ->where('published_at', '>', $cursor['published_at'])
                    ->orWhere(function (Builder $comments) use ($cursor): void {
                        $comments
                            ->where('published_at', $cursor['published_at'])
                            ->where('id', '>', $cursor['comment_id']);
                    });
            });
        }

        /** @var Collection<int, Comment> $comments */
        $comments = $query
            ->with('author:id,name,handle,headline')
            ->orderBy('published_at')
            ->orderBy('id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $comments->count() > $limit;
        $comments = $comments->take($limit)->values();

        /** @var list<int> $reportedCommentIds */
        $reportedCommentIds = CommentReport::query()
            ->where('reporter_id', $viewer->getKey())
            ->whereIn('comment_id', $comments->pluck('id')->all())
            ->pluck('comment_id')
            ->toArray();

        /** @var list<int> $visibleAuthorIds */
        $visibleAuthorIds = User::query()
            ->visibleTo($viewer)
            ->whereKey($comments->pluck('user_id')->unique()->values())
            ->pluck('id')
            ->toArray();

        $this->addViewerState($comments, $viewer, $reportedCommentIds, $visibleAuthorIds);

        $lastComment = $comments->last();
        $nextCursor = $hasMore && $lastComment instanceof Comment
            ? $cursorFactory->encode($viewer, $postModel, $lastComment)
            : null;

        $next = $nextCursor !== null
            ? route('api.v1.posts.comments', [
                'post' => $postModel,
                'cursor' => $nextCursor,
                'limit' => $limit,
            ])
            : null;

        return response()->json([
            'data' => $comments
                ->map(fn (Comment $comment): array => (new CommentResource($comment))->toArray($request))
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

    /**
     * @param  Collection<int, Comment>  $comments
     * @param  list<int>  $reportedCommentIds
     * @param  list<int>  $visibleAuthorIds
     */
    private function addViewerState(
        Collection $comments,
        User $viewer,
        array $reportedCommentIds,
        array $visibleAuthorIds,
    ): void {
        $comments->each(function (Comment $comment) use (
            $viewer,
            $reportedCommentIds,
            $visibleAuthorIds,
        ): void {
            $comment->setAttribute('viewer_can_report', $viewer->can('report', $comment));
            $comment->setAttribute(
                'viewer_has_reported',
                in_array($comment->getKey(), $reportedCommentIds, true),
            );
            $comment->setAttribute(
                'author_profile_visible',
                in_array($comment->user_id, $visibleAuthorIds, true),
            );
        });
    }

    /** @return Builder<Comment> */
    private function visibleComments(User $viewer, Post $post): Builder
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

        return Comment::query()
            ->whereBelongsTo($post)
            ->whereNotNull('published_at')
            ->whereNull('hidden_at')
            ->whereNotIn('user_id', $hiddenActorIds)
            ->whereNotIn('user_id', $blockingActorIds);
    }
}
