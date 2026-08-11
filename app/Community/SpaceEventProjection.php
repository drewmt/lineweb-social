<?php

namespace App\Community;

use App\Enums\SpaceEventRsvpStatus;
use App\Models\Space;
use App\Models\SpaceEvent;
use App\Models\SpaceEventRsvp;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class SpaceEventProjection
{
    /** @return list<array<string, mixed>> */
    public function upcoming(User $viewer, Space $space, int $limit = 20): array
    {
        return array_values($this->query($viewer, $space)
            ->where('ends_at', '>=', now())
            ->orderBy('starts_at')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(fn (SpaceEvent $event): array => $this->for($viewer, $event))
            ->all());
    }

    /** @return list<array<string, mixed>> */
    public function past(User $viewer, Space $space, int $limit = 12): array
    {
        return array_values($this->query($viewer, $space)
            ->where('ends_at', '<', now())
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (SpaceEvent $event): array => $this->for($viewer, $event))
            ->all());
    }

    /** @return array<string, mixed> */
    public function one(User $viewer, SpaceEvent $event): array
    {
        $hydrated = $this->query($viewer, $event->space)
            ->whereKey($event->getKey())
            ->firstOrFail();

        return $this->for($viewer, $hydrated);
    }

    /** @return Builder<SpaceEvent> */
    public function query(User $viewer, Space $space): Builder
    {
        return SpaceEvent::query()
            ->whereBelongsTo($space)
            ->with([
                'space' => fn ($spaces) => $spaces
                    ->select(['spaces.id', 'spaces.name', 'spaces.slug', 'spaces.visibility'])
                    ->with(['members' => fn ($members) => $members->whereKey($viewer->getKey())]),
                'rsvps' => fn ($rsvps) => $rsvps->where('user_id', $viewer->getKey()),
            ])
            ->withCount([
                'rsvps as going_count' => fn ($rsvps) => $rsvps
                    ->where('status', SpaceEventRsvpStatus::Going),
                'rsvps as interested_count' => fn ($rsvps) => $rsvps
                    ->where('status', SpaceEventRsvpStatus::Interested),
            ]);
    }

    /** @return array<string, mixed> */
    public function for(User $viewer, SpaceEvent $event): array
    {
        $viewerRsvp = $event->rsvps->first();
        $viewerStatus = $viewerRsvp instanceof SpaceEventRsvp
            ? $viewerRsvp->status
            : null;
        $goingCount = (int) $event->getAttribute('going_count');
        $isFull = $event->capacity !== null && $goingCount >= $event->capacity;

        return [
            'id' => $event->getKey(),
            'url' => route('events.show', $event),
            'title' => $event->title,
            'description' => $event->description,
            'startsAt' => $event->starts_at->toIso8601String(),
            'endsAt' => $event->ends_at->toIso8601String(),
            'timezone' => $event->timezone,
            'venue' => $event->venue,
            'onlineUrl' => $event->online_url,
            'capacity' => $event->capacity,
            'cancelledAt' => $event->cancelled_at?->toIso8601String(),
            'goingCount' => $goingCount,
            'interestedCount' => (int) $event->getAttribute('interested_count'),
            'viewerStatus' => $viewerStatus?->value,
            'canRsvp' => $viewer->can('rsvp', $event),
            'canRemoveRsvp' => $viewerStatus !== null && $viewer->can('removeRsvp', $event),
            'canCancel' => ! $event->isCancelled() && $viewer->can('cancel', $event),
            'isFull' => $isFull,
            'space' => [
                'name' => $event->space->name,
                'slug' => $event->space->slug,
            ],
        ];
    }
}
