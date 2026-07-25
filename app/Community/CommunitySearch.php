<?php

namespace App\Community;

use App\Models\Post;
use App\Models\Space;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class CommunitySearch
{
    public const int MINIMUM_QUERY_LENGTH = 2;

    public const int RESULT_LIMIT = 8;

    public function __construct(private readonly VisiblePostQuery $visiblePosts) {}

    /**
     * @return array{
     *     posts: list<array{id: int, url: string, body: string, publishedAt: string|null, author: array{name: string, handle: string, profileVisible: bool}, space: array{name: string, slug: string}}>,
     *     spaces: list<array{name: string, slug: string, description: string|null, visibility: string, memberCount: int, isMember: bool}>,
     *     people: list<array{name: string, handle: string, headline: string|null, bio: string|null, location: string|null, sharedSpaceCount: int}>,
     *     topics: list<array{name: string, url: string, visiblePostCount: int}>
     * }
     */
    public function search(User $viewer, string $query): array
    {
        if (mb_strlen($query) < self::MINIMUM_QUERY_LENGTH) {
            return $this->emptyResults();
        }

        $pattern = "%{$query}%";
        $topicQuery = ltrim($query, '#');
        $visiblePostIds = $this->visiblePosts
            ->forSearch($viewer)
            ->select('posts.id');

        $posts = $this->visiblePosts
            ->forSearch($viewer)
            ->whereLike('posts.body', $pattern)
            ->latest('posts.published_at')
            ->latest('posts.id')
            ->limit(self::RESULT_LIMIT)
            ->get();

        $visibleAuthorIds = User::query()
            ->visibleTo($viewer)
            ->whereKey($posts->pluck('user_id')->unique())
            ->pluck('id')
            ->all();

        $spaces = Space::query()
            ->discoverableBy($viewer)
            ->where(function (Builder $spaces) use ($pattern): void {
                $spaces
                    ->whereLike('spaces.name', $pattern)
                    ->orWhereLike('spaces.description', $pattern);
            })
            ->withExists([
                'members as is_member' => fn (Builder $members) => $members
                    ->whereKey($viewer->getKey()),
            ])
            ->withCount('members')
            ->orderBy('name')
            ->limit(self::RESULT_LIMIT)
            ->get();

        $people = User::query()
            ->discoverableBy($viewer)
            ->whereKeyNot($viewer->getKey())
            ->where(function (Builder $people) use ($pattern): void {
                $people
                    ->whereLike('users.name', $pattern)
                    ->orWhereLike('users.handle', $pattern)
                    ->orWhereLike('users.headline', $pattern)
                    ->orWhereLike('users.bio', $pattern)
                    ->orWhereLike('users.location', $pattern);
            })
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
            ->limit(self::RESULT_LIMIT)
            ->get();

        $topics = mb_strlen($topicQuery) >= self::MINIMUM_QUERY_LENGTH
            ? Topic::query()
                ->whereLike('topics.name', "%{$topicQuery}%")
                ->whereHas(
                    'posts',
                    fn (Builder $posts): Builder => $posts
                        ->whereIn('posts.id', clone $visiblePostIds),
                )
                ->withCount([
                    'posts as visible_posts_count' => fn (Builder $posts): Builder => $posts
                        ->whereIn('posts.id', clone $visiblePostIds),
                ])
                ->orderByDesc('visible_posts_count')
                ->orderBy('name')
                ->limit(self::RESULT_LIMIT)
                ->get()
            : collect();

        return [
            'posts' => array_values($posts
                ->map(fn (Post $post): array => [
                    'id' => $post->getKey(),
                    'url' => route('posts.show', $post),
                    'body' => $post->body,
                    'publishedAt' => $post->published_at?->toIso8601String(),
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
                ->all()),
            'spaces' => array_values($spaces
                ->map(fn (Space $space): array => [
                    'name' => $space->name,
                    'slug' => $space->slug,
                    'description' => $space->description,
                    'visibility' => $space->visibility->value,
                    'memberCount' => (int) $space->members_count,
                    'isMember' => (bool) $space->is_member,
                ])
                ->all()),
            'people' => array_values($people
                ->map(fn (User $person): array => [
                    'name' => $person->name,
                    'handle' => $person->handle,
                    'headline' => $person->headline,
                    'bio' => $person->bio,
                    'location' => $person->location,
                    'sharedSpaceCount' => (int) $person->shared_space_count,
                ])
                ->all()),
            'topics' => array_values($topics
                ->map(fn (Topic $topic): array => [
                    'name' => $topic->name,
                    'url' => route('topics.show', $topic),
                    'visiblePostCount' => (int) $topic->visible_posts_count,
                ])
                ->all()),
        ];
    }

    /**
     * @return array{posts: array{}, spaces: array{}, people: array{}, topics: array{}}
     */
    private function emptyResults(): array
    {
        return [
            'posts' => [],
            'spaces' => [],
            'people' => [],
            'topics' => [],
        ];
    }
}
