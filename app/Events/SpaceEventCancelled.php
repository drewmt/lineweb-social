<?php

namespace App\Events;

use App\Models\SpaceEvent;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SpaceEventCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly SpaceEvent $event,
        public readonly User $actor,
    ) {}
}
