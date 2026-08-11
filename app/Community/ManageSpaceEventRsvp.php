<?php

namespace App\Community;

use App\Enums\SpaceEventRsvpStatus;
use App\Events\SpaceEventRsvpChanged;
use App\Models\SpaceEvent;
use App\Models\SpaceEventRsvp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ManageSpaceEventRsvp
{
    /** @return array{changed: bool, previousStatus: SpaceEventRsvpStatus|null} */
    public function store(User $actor, SpaceEvent $event, SpaceEventRsvpStatus $status): array
    {
        $result = DB::transaction(function () use ($actor, $event, $status): array {
            $lockedEvent = SpaceEvent::query()
                ->with('space')
                ->whereKey($event->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            Gate::forUser($actor)->authorize('rsvp', $lockedEvent);

            $rsvp = SpaceEventRsvp::query()
                ->where('space_event_id', $lockedEvent->getKey())
                ->where('user_id', $actor->getKey())
                ->lockForUpdate()
                ->first();
            $previousStatus = $rsvp?->status;

            if ($previousStatus === $status) {
                return ['changed' => false, 'previousStatus' => $previousStatus, 'event' => $lockedEvent];
            }

            if ($status === SpaceEventRsvpStatus::Going && $lockedEvent->capacity !== null) {
                $goingCount = SpaceEventRsvp::query()
                    ->where('space_event_id', $lockedEvent->getKey())
                    ->where('status', SpaceEventRsvpStatus::Going)
                    ->count();

                if ($goingCount >= $lockedEvent->capacity) {
                    throw ValidationException::withMessages([
                        'status' => 'This event has reached its capacity. You can still mark yourself interested.',
                    ]);
                }
            }

            SpaceEventRsvp::query()->updateOrCreate(
                [
                    'space_event_id' => $lockedEvent->getKey(),
                    'user_id' => $actor->getKey(),
                ],
                ['status' => $status],
            );

            return ['changed' => true, 'previousStatus' => $previousStatus, 'event' => $lockedEvent];
        });

        if ($result['changed']) {
            SpaceEventRsvpChanged::dispatch(
                $result['event'],
                $actor,
                $result['previousStatus'],
                $status,
            );
        }

        return [
            'changed' => $result['changed'],
            'previousStatus' => $result['previousStatus'],
        ];
    }

    public function remove(User $actor, SpaceEvent $event): bool
    {
        $result = DB::transaction(function () use ($actor, $event): array {
            $lockedEvent = SpaceEvent::query()
                ->with('space')
                ->whereKey($event->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            Gate::forUser($actor)->authorize('removeRsvp', $lockedEvent);

            $rsvp = SpaceEventRsvp::query()
                ->where('space_event_id', $lockedEvent->getKey())
                ->where('user_id', $actor->getKey())
                ->lockForUpdate()
                ->first();

            if (! $rsvp instanceof SpaceEventRsvp) {
                return ['changed' => false, 'previousStatus' => null, 'event' => $lockedEvent];
            }

            $previousStatus = $rsvp->status;
            $rsvp->delete();

            return ['changed' => true, 'previousStatus' => $previousStatus, 'event' => $lockedEvent];
        });

        if ($result['changed']) {
            SpaceEventRsvpChanged::dispatch(
                $result['event'],
                $actor,
                $result['previousStatus'],
                null,
            );
        }

        return $result['changed'];
    }
}
