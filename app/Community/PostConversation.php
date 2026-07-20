<?php

namespace App\Community;

use App\Enums\UserRelationshipType;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\Post;
use App\Models\PostReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PostConversation
{
    private const COMMENTS_PER_PAGE = 20;

    /**
     * Build the policy-filtered permalink projection for one post.
     *
     * @return array{
     *     post: array{id: int, url: string, body: string, publishedAt: string|null, isDraft: bool, isHidden: bool, canComment: bool, canReport: bool, hasReported: bool, commentsCount: int, author: array{name: string, handle: string, profileVisible: bool}, space: array{name: string, slug: string, description: string|null, visibility: string, memberCount: int}},
     *     comments: array{data: list<array{id: int, body: string, publishedAt: string, canReport: bool, hasReported: bool, author: array{name: string, handle: string, profileVisible: bool}}>, meta: array{currentPage: int, lastPage: int, perPage: int, total: int}, links: array{newer: string|null, older: string|null}}
     * }
     */
    public function for(User $viewer, Post $post): array
    {
        $post->loadMissing([
            'author:id,name,handle',
            'space:id,name,slug,description,visibility',
        ]);
        $post->space->loadCount('members');

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
            ->where('type', UserRelationshipType::Block);

        $comments = Comment::query()
            ->whereBelongsTo($post)
            ->whereNotNull('published_at')
            ->whereNull('hidden_at')
            ->whereNotIn('user_id', clone $hiddenActorIds)
            ->whereNotIn('user_id', clone $blockingActorIds)
            ->with('author:id,name,handle')
            ->latest('published_at')
            ->latest('id')
            ->paginate(self::COMMENTS_PER_PAGE)
            ->withQueryString();

        $commentModels = $comments->getCollection();
        $visibleAuthorIds = User::query()
            ->visibleTo($viewer)
            ->whereKey($commentModels->pluck('user_id')->push($post->user_id)->unique())
            ->pluck('id')
            ->all();
        $reportedCommentIds = CommentReport::query()
            ->where('reporter_id', $viewer->getKey())
            ->whereIn('comment_id', $commentModels->pluck('id')->all())
            ->pluck('comment_id')
            ->all();

        $commentData = [];

        foreach ($commentModels->reverse() as $comment) {
            $comment->setRelation('post', $post);
            $commentData[] = [
                'id' => $comment->id,
                'body' => $comment->body,
                'publishedAt' => $comment->published_at->toIso8601String(),
                'canReport' => $viewer->can('report', $comment),
                'hasReported' => in_array($comment->id, $reportedCommentIds, true),
                'author' => [
                    'name' => $comment->author->name,
                    'handle' => $comment->author->handle,
                    'profileVisible' => in_array($comment->user_id, $visibleAuthorIds, true),
                ],
            ];
        }

        return [
            'post' => [
                'id' => $post->id,
                'url' => route('posts.show', $post),
                'body' => $post->body,
                'publishedAt' => $post->published_at?->toIso8601String(),
                'isDraft' => $post->published_at === null,
                'isHidden' => $post->hidden_at !== null,
                'canComment' => $viewer->can('comment', $post),
                'canReport' => $viewer->can('report', $post),
                'hasReported' => PostReport::query()
                    ->where('post_id', $post->getKey())
                    ->where('reporter_id', $viewer->getKey())
                    ->exists(),
                'commentsCount' => $comments->total(),
                'author' => [
                    'name' => $post->author->name,
                    'handle' => $post->author->handle,
                    'profileVisible' => in_array($post->user_id, $visibleAuthorIds, true),
                ],
                'space' => [
                    'name' => $post->space->name,
                    'slug' => $post->space->slug,
                    'description' => $post->space->description,
                    'visibility' => $post->space->visibility->value,
                    'memberCount' => $post->space->members_count,
                ],
            ],
            'comments' => [
                'data' => $commentData,
                'meta' => [
                    'currentPage' => $comments->currentPage(),
                    'lastPage' => $comments->lastPage(),
                    'perPage' => $comments->perPage(),
                    'total' => $comments->total(),
                ],
                'links' => [
                    'newer' => $comments->previousPageUrl(),
                    'older' => $comments->nextPageUrl(),
                ],
            ],
        ];
    }
}
