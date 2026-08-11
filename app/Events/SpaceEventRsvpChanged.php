<?php

namespace App\Events;

use App\Enums\SpaceEventRsvpStatus;
use App\Models\SpaceEvent;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SpaceEventRsvpChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly SpaceEvent $event,
        public readonly User $actor,
        public readonly ?SpaceEventRsvpStatus $previousStatus,
        public readonly ?SpaceEventRsvpStatus $status,
    ) {}
}
