<?php

namespace App\Community;

use App\Enums\UserRelationshipType;
use App\Models\User;
use App\Models\UserRelationship;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ManageUserSafety
{
    public function set(User $actor, User $target, UserRelationshipType $type): void
    {
        $this->ensureDifferentUsers($actor, $target);

        DB::transaction(function () use ($actor, $target, $type): void {
            if ($type === UserRelationshipType::Block) {
                UserRelationship::query()
                    ->whereBelongsTo($actor, 'actor')
                    ->whereBelongsTo($target, 'target')
                    ->delete();
            } elseif ($actor->hasBlocked($target)) {
                throw ValidationException::withMessages([
                    'relationship' => 'Unblock this person before changing mute settings.',
                ]);
            }

            UserRelationship::query()->firstOrCreate([
                'actor_id' => $actor->getKey(),
                'target_id' => $target->getKey(),
                'type' => $type,
            ]);
        });
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
