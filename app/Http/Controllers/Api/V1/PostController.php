<?php

namespace App\Http\Controllers\Api\V1;

use App\Community\Mentions\MentionProjection;
use App\Community\Polls\PostPollProjection;
use App\Community\PostReactionProjection;
use App\Community\PostShareProjection;
use App\Community\VisiblePostQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PostResource;
use App\Models\Post;
use App\Models\PostReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PostController extends Controller
{
    public function __invoke(
        Request $request,
        string $post,
        VisiblePostQuery $visiblePosts,
        PostReactionProjection $reactions,
        MentionProjection $mentions,
        PostShareProjection $shares,
        PostPollProjection $polls,
    ): JsonResponse {
        /** @var User $viewer */
        $viewer = $request->user();

        $postModel = $visiblePosts->forFeed($viewer)
            ->whereKey($post)
            ->firstOrFail();

        $this->addViewerState(
            new Collection([$postModel]),
            $viewer,
            $reactions,
            $shares,
            $polls,
        );
        $resolvedMentions = $mentions->resolve($viewer, [$postModel->body]);
        $postModel->setAttribute(
            'content_mentions',
            $mentions->forBody($postModel->body, $resolvedMentions),
        );

        return response()->json([
            'data' => (new PostResource($postModel))->toArray($request),
        ]);
    }

    /** @param Collection<int, Post> $posts */
    private function addViewerState(
        Collection $posts,
        User $viewer,
        PostReactionProjection $reactions,
        PostShareProjection $shares,
        PostPollProjection $polls,
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
        $shareProjection = $shares->forPosts($posts, $viewer);
        $pollProjection = $polls->forPosts($posts, $viewer);

        $posts->each(function (Post $post) use (
            $viewer,
            $visibleAuthorIds,
            $reportedPostIds,
            $memberSpaceIds,
            $reactionProjection,
            $shareProjection,
            $pollProjection,
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
            $post->setAttribute('share', $shareProjection[$post->getKey()] ?? null);
            $post->setAttribute(
                'viewer_can_share',
                ! isset($pollProjection[$post->getKey()])
                    && $viewer->can('share', $post),
            );
            $post->setAttribute('poll_summary', $pollProjection[$post->getKey()] ?? null);
        });
    }
}
