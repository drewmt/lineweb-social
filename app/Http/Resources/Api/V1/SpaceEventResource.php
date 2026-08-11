<?php

namespace App\Http\Resources\Api\V1;

use App\Models\SpaceEvent;
use App\Models\SpaceEventRsvp;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SpaceEvent */
class SpaceEventResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var SpaceEvent $event */
        $event = $this->resource;
        /** @var User $viewer */
        $viewer = $request->user();
        $viewerRsvp = $event->rsvps->first();
        $viewerStatus = $viewerRsvp instanceof SpaceEventRsvp
            ? $viewerRsvp->status->value
            : null;
        $goingCount = (int) $event->getAttribute('going_count');

        return [
            'id' => (string) $event->getKey(),
            'title' => $event->title,
            'description' => $event->description,
            'starts_at' => $event->starts_at->toIso8601String(),
            'ends_at' => $event->ends_at->toIso8601String(),
            'timezone' => $event->timezone,
            'venue' => $event->venue,
            'online_url' => $event->online_url,
            'capacity' => $event->capacity,
            'cancelled_at' => $event->cancelled_at?->toIso8601String(),
            'rsvp' => [
                'going_count' => $goingCount,
                'interested_count' => (int) $event->getAttribute('interested_count'),
                'viewer_status' => $viewerStatus,
                'is_full' => $event->capacity !== null && $goingCount >= $event->capacity,
                'can_respond' => $viewer->can('rsvp', $event),
            ],
            'space' => [
                'name' => $event->space->name,
                'slug' => $event->space->slug,
            ],
        ];
    }
}
