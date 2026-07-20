<?php

namespace App\Policies;

use App\Enums\SpaceRole;
use App\Enums\SpaceVisibility;
use App\Models\Space;
use App\Models\SpaceInvitation;
use App\Models\User;

class SpacePolicy
{
    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail();
    }

    public function view(User $user, Space $space): bool
    {
        return $space->visibility === SpaceVisibility::Public || $space->hasMember($user);
    }

    public function createPost(User $user, Space $space): bool
    {
        return $space->hasMember($user);
    }

    public function join(User $user, Space $space): bool
    {
        return $space->visibility === SpaceVisibility::Public
            && ! $space->hasMember($user);
    }

    public function leave(User $user, Space $space): bool
    {
        return $space->owner_id !== $user->getKey()
            && $space->hasMember($user);
    }

    public function moderate(User $user, Space $space): bool
    {
        return in_array($space->roleFor($user), [SpaceRole::Owner, SpaceRole::Moderator], true);
    }

    public function invite(User $user, Space $space, SpaceRole $role): bool
    {
        $actorRole = $space->roleFor($user);

        return $actorRole === SpaceRole::Owner
            || ($actorRole === SpaceRole::Moderator && $role === SpaceRole::Member);
    }

    public function changeMemberRole(User $user, Space $space, User $member): bool
    {
        return $space->owner_id === $user->getKey()
            && $space->owner_id !== $member->getKey();
    }

    public function transferOwnership(User $user, Space $space): bool
    {
        return $space->owner_id === $user->getKey();
    }

    public function removeMember(User $user, Space $space, User $member): bool
    {
        if ($user->is($member)) {
            return false;
        }

        $actorRole = $space->roleFor($user);
        $memberRole = $space->roleFor($member);

        if ($memberRole === null || $memberRole === SpaceRole::Owner) {
            return false;
        }

        return $actorRole === SpaceRole::Owner
            || ($actorRole === SpaceRole::Moderator && $memberRole === SpaceRole::Member);
    }

    public function revokeInvitation(User $user, Space $space, SpaceInvitation $invitation): bool
    {
        if ($invitation->space_id !== $space->getKey()) {
            return false;
        }

        return $this->invite($user, $space, $invitation->role);
    }
}
