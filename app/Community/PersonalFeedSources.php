<?php

namespace App\Community;

use App\Models\PersonalFeed;
use App\Models\Post;
use App\Models\Space;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type Source array{id: int, label: string, reference: string}
 * @phpstan-type Selection array{people: list<Source>, spaces: list<Source>, topics: list<Source>}
 * @phpstan-type SourceIds array{people: list<int>, spaces: list<int>, topics: list<int>}
 */
final class PersonalFeedSources
{
    public const int MAX_FEEDS = 20;

    public const int MAX_SOURCES = 30;

    private const int SEARCH_LIMIT = 12;

    public function __construct(private readonly VisiblePostQuery $visiblePosts) {}

    /** @return Selection */
    public function search(User $viewer, string $term): array
    {
        $pattern = '%'.$term.'%';

        return $this->project(
            $this->people($viewer)->where(fn (Builder $users) => $users
                ->whereLike('users.name', $pattern)
                ->orWhereLike('users.handle', '%'.ltrim($term, '@').'%'))->limit(self::SEARCH_LIMIT),
            $this->spaces($viewer)->where(fn (Builder $spaces) => $spaces
                ->whereLike('spaces.name', $pattern)
                ->orWhereLike('spaces.slug', $pattern))->limit(self::SEARCH_LIMIT),
            $this->topics($viewer)->whereLike('topics.name', '%'.ltrim($term, '#').'%')->limit(self::SEARCH_LIMIT),
        );
    }

    /** @return Selection */
    public function selected(User $viewer, PersonalFeed $feed): array
    {
        return $this->project(
            $this->people($viewer)->whereIn('users.id', DB::table('personal_feed_people')
                ->where('personal_feed_id', $feed->id)->select('person_id')),
            $this->spaces($viewer)->whereIn('spaces.id', DB::table('personal_feed_spaces')
                ->where('personal_feed_id', $feed->id)->select('space_id')),
            $this->topics($viewer)->whereIn('topics.id', DB::table('personal_feed_topics')
                ->where('personal_feed_id', $feed->id)->select('topic_id')),
        );
    }

    /**
     * @param  SourceIds  $ids
     */
    public function sync(User $viewer, PersonalFeed $feed, array $ids): void
    {
        if (array_sum(array_map(count(...), $ids)) > self::MAX_SOURCES) {
            throw ValidationException::withMessages(['sources' => 'Choose at most '.self::MAX_SOURCES.' sources.']);
        }

        if ($this->people($viewer)->whereKey($ids['people'])->count() !== count($ids['people'])
            || $this->spaces($viewer)->whereKey($ids['spaces'])->count() !== count($ids['spaces'])
            || $this->topics($viewer)->whereKey($ids['topics'])->count() !== count($ids['topics'])) {
            throw ValidationException::withMessages(['sources' => 'One or more sources are unavailable. Update your selection and try again.']);
        }

        $feed->people()->sync($ids['people']);
        $feed->spaces()->sync($ids['spaces']);
        $feed->topics()->sync($ids['topics']);
    }

    /** @return Builder<User> */
    private function people(User $viewer): Builder
    {
        return User::query()->discoverableBy($viewer)->whereKeyNot($viewer->id);
    }

    /** @return Builder<Space> */
    private function spaces(User $viewer): Builder
    {
        return Space::query()->discoverableBy($viewer);
    }

    /** @return Builder<Topic> */
    private function topics(User $viewer): Builder
    {
        return Topic::query()->whereHas('posts', fn (Builder $posts) => $posts
            ->whereIn('posts.id', $this->visiblePosts->forSearch($viewer)->select('posts.id')));
    }

    /**
     * @param  Builder<User>  $people
     * @param  Builder<Space>  $spaces
     * @param  Builder<Topic>  $topics
     * @return Selection
     */
    private function project(Builder $people, Builder $spaces, Builder $topics): array
    {
        return [
            'people' => array_values($people->orderBy('users.name')->orderBy('users.id')->get(['id', 'name', 'handle'])
                ->map(fn (User $person): array => ['id' => $person->id, 'label' => $person->name, 'reference' => $person->handle])->all()),
            'spaces' => array_values($spaces->orderBy('spaces.name')->orderBy('spaces.id')->get(['id', 'name', 'slug'])
                ->map(fn (Space $space): array => ['id' => $space->id, 'label' => $space->name, 'reference' => $space->slug])->all()),
            'topics' => array_values($topics->orderBy('topics.name')->orderBy('topics.id')->get(['id', 'name'])
                ->map(fn (Topic $topic): array => ['id' => $topic->id, 'label' => '#'.$topic->name, 'reference' => $topic->name])->all()),
        ];
    }

    /**
     * @param  Builder<Post>  $posts
     * @param  SourceIds  $ids
     */
    public static function restrictPosts(Builder $posts, User $viewer, array $ids): void
    {
        if ($ids['people'] === [] && $ids['spaces'] === [] && $ids['topics'] === []) {
            $posts->whereKey([]);

            return;
        }

        $posts->where(fn (Builder $matches) => $matches
            ->whereIn('posts.user_id', User::query()->discoverableBy($viewer)->whereKey($ids['people'])->select('users.id'))
            ->orWhereIn('posts.space_id', $ids['spaces'])
            ->orWhereHas('topics', fn (Builder $topics) => $topics->whereKey($ids['topics'])));
    }
}
