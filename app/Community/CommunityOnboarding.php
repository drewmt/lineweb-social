<?php

namespace App\Community;

use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class CommunityOnboarding
{
    private const STEP_TOTAL = 4;

    public function shouldGuide(User $user): bool
    {
        return $user->onboarding_dismissed_at === null
            && ! $user->spaces()->exists();
    }

    /** @return array<string, mixed> */
    public function for(User $user): array
    {
        $steps = $this->steps($user);
        $completed = collect($steps)->where('complete', true)->count();

        return [
            'progress' => [
                'completed' => $completed,
                'total' => self::STEP_TOTAL,
                'percent' => (int) round(($completed / self::STEP_TOTAL) * 100),
            ],
            'steps' => $steps,
            'spaces' => $this->suggestedSpaces($user),
            'people' => $this->suggestedPeople($user),
        ];
    }

    /** @return list<array{key: string, title: string, description: string, href: string, action: string, complete: bool}> */
    private function steps(User $user): array
    {
        $hasSpace = $user->spaces()->exists();

        return [
            [
                'key' => 'profile',
                'title' => 'Make your profile recognizable',
                'description' => 'Add a short headline and bio so people understand who they are connecting with.',
                'href' => route('profile.edit'),
                'action' => 'Complete profile',
                'complete' => filled($user->headline) && filled($user->bio),
            ],
            [
                'key' => 'space',
                'title' => 'Find your first space',
                'description' => 'Join a public community or create a focused place for your own people.',
                'href' => route('spaces.index'),
                'action' => 'Explore spaces',
                'complete' => $hasSpace,
            ],
            [
                'key' => 'people',
                'title' => 'Follow one useful voice',
                'description' => 'Build a chronological Following feed without recommendation algorithms.',
                'href' => route('people.index'),
                'action' => 'Meet people',
                'complete' => $user->outgoingFollows()->exists(),
            ],
            [
                'key' => 'post',
                'title' => 'Start a real conversation',
                'description' => 'Publish your first post once you have joined a space.',
                'href' => $hasSpace ? route('posts.compose') : route('spaces.index'),
                'action' => $hasSpace ? 'Create a post' : 'Join a space first',
                'complete' => Post::query()
                    ->whereBelongsTo($user, 'author')
                    ->whereNotNull('published_at')
                    ->whereNull('hidden_at')
                    ->exists(),
            ],
        ];
    }

    /** @return list<array{name: string, slug: string, description: string|null, memberCount: int}> */
    private function suggestedSpaces(User $user): array
    {
        return array_values(Space::query()
            ->discoverableBy($user)
            ->whereDoesntHave('members', fn (Builder $members) => $members
                ->whereKey($user->getKey()))
            ->withCount('members')
            ->orderByDesc('members_count')
            ->orderBy('name')
            ->limit(4)
            ->get()
            ->map(fn (Space $space): array => [
                'name' => $space->name,
                'slug' => $space->slug,
                'description' => $space->description,
                'memberCount' => (int) $space->members_count,
            ])
            ->values()
            ->all());
    }

    /** @return list<array{name: string, handle: string, headline: string|null, sharedSpaceCount: int}> */
    private function suggestedPeople(User $user): array
    {
        return array_values(User::query()
            ->discoverableBy($user)
            ->whereKeyNot($user->getKey())
            ->whereDoesntHave('incomingFollows', fn (Builder $follows) => $follows
                ->where('follower_id', $user->getKey()))
            ->withCount([
                'spaces as shared_space_count' => fn (Builder $spaces) => $spaces
                    ->whereIn(
                        'spaces.id',
                        DB::table('space_members')
                            ->select('space_id')
                            ->where('user_id', $user->getKey()),
                    ),
            ])
            ->orderByDesc('shared_space_count')
            ->orderBy('name')
            ->limit(4)
            ->get()
            ->map(fn (User $person): array => [
                'name' => $person->name,
                'handle' => $person->handle,
                'headline' => $person->headline,
                'sharedSpaceCount' => (int) $person->shared_space_count,
            ])
            ->values()
            ->all());
    }
}
