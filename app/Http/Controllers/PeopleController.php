<?php

namespace App\Http\Controllers;

use App\Community\Mentions\MentionProjection;
use App\Community\Polls\PostPollProjection;
use App\Community\PostMediaView;
use App\Community\PostShareProjection;
use App\Community\VisiblePostQuery;
use App\Enums\ReportStatus;
use App\Models\Post;
use App\Models\PostReport;
use App\Models\Space;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PeopleController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $viewer */
        $viewer = $request->user();

        $people = User::query()
            ->discoverableBy($viewer)
            ->whereKeyNot($viewer->getKey())
            ->withCount([
                'spaces as shared_space_count' => fn (Builder $spaces) => $spaces
                    ->whereIn(
                        'spaces.id',
                        DB::table('space_members')
                            ->select('space_id')
                            ->where('user_id', $viewer->getKey()),
                    ),
            ])
            ->orderBy('name')
            ->limit(60)
            ->get();

        return Inertia::render('people/index', [
            'people' => $people->map(fn (User $person): array => [
                'name' => $person->name,
                'handle' => $person->handle,
                'headline' => $person->headline,
                'bio' => $person->bio,
                'location' => $person->location,
                'sharedSpaceCount' => (int) $person->shared_space_count,
            ])->values()->all(),
        ]);
    }

    public function show(
        Request $request,
        User $profile,
        PostMediaView $media,
        MentionProjection $mentions,
        PostShareProjection $shares,
        VisiblePostQuery $visiblePostQuery,
        PostPollProjection $polls,
    ): Response {
        Gate::authorize('view', $profile);

        /** @var User $viewer */
        $viewer = $request->user();
        $profile->loadCount(['followers', 'following']);

        $visibleSpaceIds = Space::query()
            ->discoverableBy($viewer)
            ->select('spaces.id');

        $visibleProfileSpaces = Space::query()
            ->discoverableBy($viewer)
            ->whereHas('members', fn (Builder $members) => $members->whereKey($profile->getKey()));

        $spaceCount = (clone $visibleProfileSpaces)->count();

        $spaces = $visibleProfileSpaces
            ->withCount('members')
            ->orderBy('name')
            ->limit(12)
            ->get()
            ->map(fn (Space $space): array => [
                'name' => $space->name,
                'slug' => $space->slug,
                'description' => $space->description,
                'memberCount' => $space->members_count,
            ])
            ->values()
            ->all();

        $visiblePosts = $visiblePostQuery
            ->forFeed($viewer)
            ->whereBelongsTo($profile, 'author')
            ->whereIn('space_id', clone $visibleSpaceIds);

        $postCount = (clone $visiblePosts)->count();

        $postModels = $visiblePosts
            ->with(['space:id,name,slug', 'media', 'mediaItems', 'topics:id,name'])
            ->latest('published_at')
            ->limit(12)
            ->get();

        $lockedPostIds = PostReport::query()
            ->whereIn('post_id', $postModels->modelKeys())
            ->whereIn('status', [
                ReportStatus::Open->value,
                ReportStatus::Reviewing->value,
            ])
            ->pluck('post_id')
            ->all();
        $resolvedMentions = $mentions->resolve($viewer, $postModels->pluck('body'));
        $shareProjection = $shares->forPosts($postModels, $viewer);
        $pollProjection = $polls->forPosts($postModels, $viewer);

        $posts = $postModels
            ->map(fn (Post $post): array => [
                'id' => $post->id,
                'url' => route('posts.show', $post),
                'body' => $post->body,
                'mentions' => $mentions->forBody($post->body, $resolvedMentions),
                'topics' => $post->topics
                    ->map(fn (Topic $topic): array => [
                        'name' => $topic->name,
                        'url' => route('topics.show', $topic),
                    ])
                    ->values()
                    ->all(),
                'media' => $media->for($post),
                'mediaItems' => $media->galleryFor($post),
                'share' => $shareProjection[$post->getKey()] ?? null,
                'poll' => $pollProjection[$post->getKey()] ?? null,
                'publishedAt' => $post->published_at?->toIso8601String(),
                'editedAt' => $post->edited_at?->toIso8601String(),
                'canEdit' => Gate::forUser($viewer)->allows('update', $post)
                    && ! isset($pollProjection[$post->getKey()])
                    && ! in_array($post->getKey(), $lockedPostIds, true),
                'canDelete' => Gate::forUser($viewer)->allows('delete', $post)
                    && ! in_array($post->getKey(), $lockedPostIds, true),
                'canShare' => ! isset($pollProjection[$post->getKey()])
                    && Gate::forUser($viewer)->allows('share', $post),
                'author' => [
                    'name' => $post->author->name,
                ],
                'space' => [
                    'name' => $post->space->name,
                    'slug' => $post->space->slug,
                ],
            ])
            ->values()
            ->all();

        return Inertia::render('people/show', [
            'profile' => [
                'name' => $profile->name,
                'handle' => $profile->handle,
                'headline' => $profile->headline,
                'bio' => $profile->bio,
                'location' => $profile->location,
                'websiteUrl' => $profile->website_url,
                'memberSince' => $profile->created_at?->toDateString(),
                'isSelf' => $profile->is($viewer),
                'isMuted' => $viewer->hasMuted($profile),
                'isFollowing' => $viewer->isFollowing($profile),
                'canFollow' => Gate::forUser($viewer)->allows('follow', $profile),
                'canMessage' => Gate::forUser($viewer)->allows('message', $profile),
            ],
            'stats' => [
                'visibleSpaces' => $spaceCount,
                'visiblePosts' => $postCount,
                'followers' => (int) $profile->followers_count,
                'following' => (int) $profile->following_count,
            ],
            'spaces' => $spaces,
            'posts' => $posts,
        ]);
    }
}
