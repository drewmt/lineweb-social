<?php

namespace App\Community;

use App\Enums\UserRelationshipType;
use App\Events\UserFollowChanged;
use App\Models\User;
use App\Models\UserFollow;
use App\Models\UserRelationship;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ManageUserSafety
{
    public function set(User $actor, User $target, UserRelationshipType $type): void
    {
        $this->ensureDifferentUsers($actor, $target);

        $removedFollows = DB::transaction(function () use ($actor, $target, $type): array {
            $users = User::query()
                ->whereKey([$actor->getKey(), $target->getKey()])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (User $user): int => $user->getKey());
            $lockedActor = $users->get($actor->getKey()) ?? $actor;
            $lockedTarget = $users->get($target->getKey()) ?? $target;
            $removedFollows = [];

            if ($type === UserRelationshipType::Block) {
                UserRelationship::query()
                    ->whereBelongsTo($lockedActor, 'actor')
                    ->whereBelongsTo($lockedTarget, 'target')
                    ->delete();

                $removedFollows = UserFollow::query()
                    ->where(function ($query) use ($lockedActor, $lockedTarget): void {
                        $query
                            ->where(function ($pair) use ($lockedActor, $lockedTarget): void {
                                $pair
                                    ->where('follower_id', $lockedActor->getKey())
                                    ->where('followed_id', $lockedTarget->getKey());
                            })
                            ->orWhere(function ($pair) use ($lockedActor, $lockedTarget): void {
                                $pair
                                    ->where('follower_id', $lockedTarget->getKey())
                                    ->where('followed_id', $lockedActor->getKey());
                            });
                    })
                    ->get()
                    ->map(fn (UserFollow $follow): array => [
                        'follower_id' => $follow->follower_id,
                        'followed_id' => $follow->followed_id,
                    ])
                    ->all();

                UserFollow::query()
                    ->where(function ($query) use ($lockedActor, $lockedTarget): void {
                        $query
                            ->where(function ($pair) use ($lockedActor, $lockedTarget): void {
                                $pair
                                    ->where('follower_id', $lockedActor->getKey())
                                    ->where('followed_id', $lockedTarget->getKey());
                            })
                            ->orWhere(function ($pair) use ($lockedActor, $lockedTarget): void {
                                $pair
                                    ->where('follower_id', $lockedTarget->getKey())
                                    ->where('followed_id', $lockedActor->getKey());
                            });
                    })
                    ->delete();
            } elseif ($lockedActor->hasBlocked($lockedTarget)) {
                throw ValidationException::withMessages([
                    'relationship' => 'Unblock this person before changing mute settings.',
                ]);
            }

            UserRelationship::query()->firstOrCreate([
                'actor_id' => $lockedActor->getKey(),
                'target_id' => $lockedTarget->getKey(),
                'type' => $type,
            ]);

            return $removedFollows;
        });

        foreach ($removedFollows as $follow) {
            $follower = (int) $follow['follower_id'] === $actor->getKey() ? $actor : $target;
            $followed = (int) $follow['followed_id'] === $target->getKey() ? $target : $actor;

            UserFollowChanged::dispatch($follower, $followed, false);
        }
    }

    public function remove(User $actor, User $target, UserRelationshipType $type): void
    {
        $this->ensureDifferentUsers($actor, $target);

        UserRelationship::query()
            ->whereBelongsTo($actor, 'actor')
            ->whereBelongsTo($target, 'target')
            ->where('type', $type)
            ->delete();
    }

    private function ensureDifferentUsers(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            throw ValidationException::withMessages([
                'relationship' => 'You cannot mute or block your own account.',
            ]);
        }
    }
}
