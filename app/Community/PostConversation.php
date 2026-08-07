<?php

namespace App\Community;

use App\Community\Mentions\MentionProjection;
use App\Enums\ReportStatus;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\Post;
use App\Models\PostReport;
use App\Models\SpacePostHighlight;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class PostConversation
{
    private const COMMENTS_PER_PAGE = 20;

    public function __construct(
        private readonly PostMediaView $media,
        private readonly PostReactionProjection $reactions,
        private readonly MentionProjection $mentions,
        private readonly VisibleCommentQuery $visibleComments,
        private readonly CommentReplyProjection $replyContexts,
        private readonly PostShareProjection $shares,
    ) {}

    /**
     * Build the policy-filtered permalink projection for one post.
     *
     * @return array{
     *     post: array{id: int, url: string, body: string, media: array{url: string, alt: string, width: int, height: int}|null, mediaItems: list<array{id: int, url: string, alt: string, width: int, height: int}>, publishedAt: string|null, editedAt: string|null, isHighlighted: bool, highlightedAt: string|null, isDraft: bool, isHidden: bool, isSaved: bool, canComment: bool, canReport: bool, canEdit: bool, canDelete: bool, hasReported: bool, commentsCount: int, author: array{name: string, handle: string, profileVisible: bool}, space: array{name: string, slug: string, description: string|null, visibility: string, memberCount: int}},
     *     comments: array{data: list<array{id: int, body: string, publishedAt: string, editedAt: string|null, isReply: bool, replyTo: array{id: int, author: array{name: string, handle: string, profileVisible: bool}}|null, canReport: bool, canEdit: bool, canDelete: bool, hasReported: bool, author: array{name: string, handle: string, profileVisible: bool}}>, meta: array{currentPage: int, lastPage: int, perPage: int, total: int}, links: array{newer: string|null, older: string|null}}
     * }
     */
    public function for(User $viewer, Post $post): array
    {
        $post->loadMissing([
            'author:id,name,handle',
            'space:id,name,slug,description,visibility',
            'media',
            'mediaItems',
            'highlight',
            'topics:id,name',
            'sharedPost' => fn ($shared) => $shared->with([
                'author:id,name,handle',
                'space:id,name,slug',
                'mediaItems',
            ]),
        ]);
        $post->space->loadCount('members');
        $post->loadExists([
            'saves as is_saved' => fn ($saves) => $saves
                ->where('user_id', $viewer->getKey()),
        ]);
        $reactionProjection = $this->reactions->forPosts(
            new Collection([$post]),
            $viewer,
        );
        $shareProjection = $this->shares->forPosts(
            new Collection([$post]),
            $viewer,
        );

        $comments = $this->visibleComments->forPost($viewer, $post)
            ->with('author:id,name,handle')
            ->latest('published_at')
            ->latest('id')
            ->paginate(self::COMMENTS_PER_PAGE)
            ->withQueryString();

        $commentModels = $comments->getCollection();
        $replyContexts = $this->replyContexts->for($viewer, $commentModels);
        $resolvedMentions = $this->mentions->resolve(
            $viewer,
            $commentModels->pluck('body')->push($post->body),
        );
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
        $activeStatuses = [
            ReportStatus::Open->value,
            ReportStatus::Reviewing->value,
        ];
        $postIsLocked = PostReport::query()
            ->where('post_id', $post->getKey())
            ->whereIn('status', $activeStatuses)
            ->exists();
        $lockedCommentIds = CommentReport::query()
            ->whereIn('comment_id', $commentModels->pluck('id')->all())
            ->whereIn('status', $activeStatuses)
            ->pluck('comment_id')
            ->all();

        $commentData = [];

        foreach ($commentModels->reverse() as $comment) {
            $comment->setRelation('post', $post);
            $commentData[] = [
                'id' => $comment->id,
                'body' => $comment->body,
                'mentions' => $this->mentions->forBody($comment->body, $resolvedMentions),
                'publishedAt' => $comment->published_at->toIso8601String(),
                'editedAt' => $comment->edited_at?->toIso8601String(),
                'isReply' => $comment->parent_id !== null,
                'replyTo' => $comment->parent_id !== null
                    ? ($replyContexts[$comment->getKey()] ?? null)
                    : null,
                'canReport' => $viewer->can('report', $comment),
                'canEdit' => $viewer->can('update', $comment)
                    && ! in_array($comment->getKey(), $lockedCommentIds, true),
                'canDelete' => $viewer->can('delete', $comment)
                    && ! in_array($comment->getKey(), $lockedCommentIds, true),
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
                'mentions' => $this->mentions->forBody($post->body, $resolvedMentions),
                'topics' => $post->topics
                    ->map(fn (Topic $topic): array => [
                        'name' => $topic->name,
                        'url' => route('topics.show', $topic),
                    ])
                    ->values()
                    ->all(),
                'media' => $this->media->for($post),
                'mediaItems' => $this->media->galleryFor($post),
                'publishedAt' => $post->published_at?->toIso8601String(),
                'editedAt' => $post->edited_at?->toIso8601String(),
                'isHighlighted' => $post->highlight instanceof SpacePostHighlight,
                'highlightedAt' => $post->highlight?->created_at->toIso8601String(),
                'isDraft' => $post->published_at === null,
                'isHidden' => $post->hidden_at !== null,
                'isSaved' => (bool) $post->is_saved,
                'reactions' => $reactionProjection[$post->getKey()],
                'share' => $shareProjection[$post->getKey()] ?? null,
                'canComment' => $viewer->can('comment', $post),
                'canShare' => $viewer->can('share', $post),
                'canReport' => $viewer->can('report', $post),
                'canEdit' => $viewer->can('update', $post) && ! $postIsLocked,
                'canDelete' => $viewer->can('delete', $post) && ! $postIsLocked,
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

    public function urlForComment(User $viewer, Comment $comment): ?string
    {
        $comment->loadMissing('post');
        $query = $this->visibleComments->forPost($viewer, $comment->post);

        if (! (clone $query)->whereKey($comment->getKey())->exists()) {
            return null;
        }

        $newerComments = (clone $query)
            ->where(function (Builder $newer) use ($comment): void {
                $newer
                    ->where('published_at', '>', $comment->published_at)
                    ->orWhere(function (Builder $sameTime) use ($comment): void {
                        $sameTime
                            ->where('published_at', $comment->published_at)
                            ->where('id', '>', $comment->id);
                    });
            })
            ->count();
        $page = intdiv($newerComments, self::COMMENTS_PER_PAGE) + 1;
        $parameters = ['post' => $comment->post];

        if ($page > 1) {
            $parameters['page'] = $page;
        }

        return route('posts.show', $parameters).'#comment-'.$comment->id;
    }
}
