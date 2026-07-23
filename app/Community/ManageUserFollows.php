<?php

namespace App\Community;

use App\Events\UserFollowChanged;
use App\Models\User;
use App\Models\UserFollow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ManageUserFollows
{
    public function follow(User $follower, User $followed): bool
    {
        Gate::forUser($follower)->authorize('follow', $followed);

        $changed = DB::transaction(function () use ($follower, $followed): bool {
            [$lockedFollower, $lockedFollowed] = $this->lockPair($follower, $followed);

            Gate::forUser($lockedFollower)->authorize('follow', $lockedFollowed);

            return UserFollow::query()->firstOrCreate([
                'follower_id' => $lockedFollower->getKey(),
                'followed_id' => $lockedFollowed->getKey(),
            ])->wasRecentlyCreated;
        });

        if ($changed) {
            UserFollowChanged::dispatch($follower, $followed, true);
        }

        return $changed;
    }

    public function unfollow(User $follower, User $followed): bool
    {
        if ($follower->is($followed)) {
            return false;
        }

        $changed = DB::transaction(function () use ($follower, $followed): bool {
            [$lockedFollower, $lockedFollowed] = $this->lockPair($follower, $followed);

            return UserFollow::query()
                ->whereBelongsTo($lockedFollower, 'follower')
                ->whereBelongsTo($lockedFollowed, 'followed')
                ->delete() > 0;
        });

        if ($changed) {
            UserFollowChanged::dispatch($follower, $followed, false);
        }

        return $changed;
    }

    /** @return array{User, User} */
    private function lockPair(User $first, User $second): array
    {
        $users = User::query()
            ->whereKey([$first->getKey(), $second->getKey()])
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (User $user): int => $user->getKey());

        return [
            $users->get($first->getKey()) ?? $first,
            $users->get($second->getKey()) ?? $second,
        ];
    }
}
