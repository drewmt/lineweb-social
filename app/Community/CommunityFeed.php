<?php

namespace App\Community;

use App\Enums\ReportStatus;
use App\Enums\SpaceRole;
use App\Enums\UserRelationshipType;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\Post;
use App\Models\PostReport;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class CommunityFeed
{
    public function __construct(private readonly PostMediaView $media) {}

    /**
     * @return list<array{name: string, slug: string, description: string|null, visibility: 'public'|'private'|'hidden', memberCount: int, isMember: bool, isOwner: bool, canManage: bool}>
     */
    public function spaces(User $user): array
    {
        return array_values(Space::query()
            ->discoverableBy($user)
            ->addSelect([
                'current_role' => DB::table('space_members')
                    ->select('role')
                    ->whereColumn('space_members.space_id', 'spaces.id')
                    ->where('space_members.user_id', $user->getKey())
                    ->limit(1),
            ])
            ->withExists(['members as is_member' => fn (Builder $query) => $query->whereKey($user->getKey())])
            ->withCount('members')
            ->orderBy('name')
            ->get()
            ->map(fn (Space $space): array => [
                'name' => $space->name,
                'slug' => $space->slug,
                'description' => $space->description,
                'visibility' => $space->visibility->value,
                'memberCount' => $space->members_count,
                'isMember' => $space->is_member,
                'isOwner' => $space->owner_id === $user->getKey(),
                'canManage' => in_array(
                    is_string($space->current_role) ? SpaceRole::tryFrom($space->current_role) : null,
                    [SpaceRole::Owner, SpaceRole::Moderator],
                    true,
                ),
            ])
            ->all());
    }

    /**
     * @return list<array{id: int, url: string, body: string, media: array{url: string, alt: string, width: int, height: int}|null, publishedAt: string|null, editedAt: string|null, isSaved: bool, canComment: bool, canReport: bool, canEdit: bool, canDelete: bool, hasReported: bool, commentsCount: int, comments: list<array{id: int, body: string, publishedAt: string, editedAt: string|null, canReport: bool, canEdit: bool, canDelete: bool, hasReported: bool, author: array{name: string, handle: string, profileVisible: bool}}>, author: array{name: string, handle: string, profileVisible: bool}, space: array{name: string, slug: string}}>
     */
    public function posts(User $user, ?Space $space = null, bool $savedOnly = false): array
    {
        $hiddenActorIds = DB::table('user_relationships')
            ->select('target_id')
            ->where('actor_id', $user->getKey())
            ->whereIn('type', [
                UserRelationshipType::Mute->value,
                UserRelationshipType::Block->value,
            ]);
        $blockingActorIds = DB::table('user_relationships')
            ->select('actor_id')
            ->where('target_id', $user->getKey())
            ->where('type', UserRelationshipType::Block);
        $visibleComments = fn ($comments) => $comments
            ->whereNotNull('published_at')
            ->whereNull('hidden_at')
            ->whereNotIn('user_id', clone $hiddenActorIds)
            ->whereNotIn('user_id', clone $blockingActorIds);

        $query = Post::query()
            ->whereNotNull('posts.published_at')
            ->whereNull('posts.hidden_at')
            ->whereNotIn('posts.user_id', clone $hiddenActorIds)
            ->whereNotIn('posts.user_id', clone $blockingActorIds)
            ->with([
                'author:id,name,handle',
                'space:id,name,slug,visibility',
                'media',
                'comments' => fn ($comments) => $visibleComments($comments)
                    ->with('author:id,name,handle')
                    ->latest('published_at')
                    ->latest('id')
                    ->limit(6),
            ])
            ->withCount(['comments as comments_count' => $visibleComments])
            ->withExists([
                'saves as is_saved' => fn ($saves) => $saves
                    ->where('user_id', $user->getKey()),
            ]);

        if ($savedOnly) {
            $query
                ->join('post_saves', function ($join) use ($user): void {
                    $join
                        ->on('post_saves.post_id', '=', 'posts.id')
                        ->where('post_saves.user_id', $user->getKey());
                })
                ->addSelect('posts.*');
        }

        if ($space instanceof Space) {
            $query->whereBelongsTo($space);
        } else {
            $query->whereIn(
                'posts.space_id',
                Space::query()->discoverableBy($user)->select('id'),
            );
        }

        if ($savedOnly) {
            $query
                ->orderByDesc('post_saves.created_at')
                ->orderByDesc('post_saves.id');
        } else {
            $query
                ->latest('posts.published_at')
                ->latest('posts.id');
        }

        $posts = $query->limit(30)->get();

        $comments = $posts->flatMap(fn (Post $post) => $post->comments);
        $visibleAuthorIds = User::query()
            ->visibleTo($user)
            ->whereKey($posts->pluck('user_id')->merge($comments->pluck('user_id'))->unique())
            ->pluck('id')
            ->all();

        $reportedPostIds = PostReport::query()
            ->where('reporter_id', $user->getKey())
            ->whereIn('post_id', $posts->modelKeys())
            ->pluck('post_id')
            ->all();

        $reportedCommentIds = CommentReport::query()
            ->where('reporter_id', $user->getKey())
            ->whereIn('comment_id', $comments->pluck('id')->all())
            ->pluck('comment_id')
            ->all();

        $activeStatuses = [
            ReportStatus::Open->value,
            ReportStatus::Reviewing->value,
        ];
        $lockedPostIds = PostReport::query()
            ->whereIn('post_id', $posts->modelKeys())
            ->whereIn('status', $activeStatuses)
            ->pluck('post_id')
            ->all();
        $lockedCommentIds = CommentReport::query()
            ->whereIn('comment_id', $comments->pluck('id')->all())
            ->whereIn('status', $activeStatuses)
            ->pluck('comment_id')
            ->all();

        $memberSpaceIds = DB::table('space_members')
            ->where('user_id', $user->getKey())
            ->pluck('space_id')
            ->all();

        return array_values($posts
            ->map(fn (Post $post): array => [
                'id' => $post->id,
                'url' => route('posts.show', $post),
                'body' => $post->body,
                'media' => $this->media->for($post),
                'publishedAt' => $post->published_at?->toIso8601String(),
                'editedAt' => $post->edited_at?->toIso8601String(),
                'isSaved' => (bool) $post->is_saved,
                'canComment' => in_array($post->space_id, $memberSpaceIds, true),
                'canReport' => $post->user_id !== $user->getKey(),
                'canEdit' => $post->user_id === $user->getKey()
                    && ! in_array($post->getKey(), $lockedPostIds, true),
                'canDelete' => $post->user_id === $user->getKey()
                    && ! in_array($post->getKey(), $lockedPostIds, true),
                'hasReported' => in_array($post->getKey(), $reportedPostIds, true),
                'commentsCount' => (int) $post->comments_count,
                'comments' => array_values($post->comments
                    ->reverse()
                    ->map(fn (Comment $comment): array => [
                        'id' => $comment->getKey(),
                        'body' => $comment->body,
                        'publishedAt' => $comment->published_at->toIso8601String(),
                        'editedAt' => $comment->edited_at?->toIso8601String(),
                        'canReport' => $comment->user_id !== $user->getKey(),
                        'canEdit' => $comment->user_id === $user->getKey()
                            && ! in_array($comment->getKey(), $lockedCommentIds, true),
                        'canDelete' => $comment->user_id === $user->getKey()
                            && ! in_array($comment->getKey(), $lockedCommentIds, true),
                        'hasReported' => in_array($comment->getKey(), $reportedCommentIds, true),
                        'author' => [
                            'name' => $comment->author->name,
                            'handle' => $comment->author->handle,
                            'profileVisible' => in_array($comment->user_id, $visibleAuthorIds, true),
                        ],
                    ])
                    ->all()),
                'author' => [
                    'name' => $post->author->name,
                    'handle' => $post->author->handle,
                    'profileVisible' => in_array($post->user_id, $visibleAuthorIds, true),
                ],
                'space' => [
                    'name' => $post->space->name,
                    'slug' => $post->space->slug,
                ],
            ])
            ->all());
    }
}
