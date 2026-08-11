<?php

namespace App\Policies;

use App\Models\Space;
use App\Models\SpaceEvent;
use App\Models\User;

class SpaceEventPolicy
{
    public function create(User $user, Space $space): bool
    {
        return (new SpacePolicy)->moderate($user, $space);
    }

    public function view(User $user, SpaceEvent $event): bool
    {
        return (new SpacePolicy)->view($user, $event->space);
    }

    public function rsvp(User $user, SpaceEvent $event): bool
    {
        return $this->view($user, $event)
            && $event->space->hasMember($user)
            && ! $event->isCancelled()
            && ! $event->hasStarted();
    }

    public function cancel(User $user, SpaceEvent $event): bool
    {
        return (new SpacePolicy)->moderate($user, $event->space)
            && ! $event->ends_at->isPast();
    }

    public function removeRsvp(User $user, SpaceEvent $event): bool
    {
        return $this->view($user, $event) && $event->space->hasMember($user);
    }
}
