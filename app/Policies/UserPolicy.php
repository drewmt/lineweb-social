<?php

namespace App\Policies;

use App\Enums\ProfileVisibility;
use App\Models\User;

class UserPolicy
{
    public function view(User $viewer, User $profile): bool
    {
        return $viewer->is($profile)
            || (! $viewer->isBlockedWith($profile)
                && ($profile->profile_visibility === ProfileVisibility::Public
                    || ($profile->profile_visibility === ProfileVisibility::Members
                        && $profile->sharesSpaceWith($viewer))));
    }

    public function follow(User $viewer, User $profile): bool
    {
        return ! $viewer->is($profile) && $this->view($viewer, $profile);
    }
}
