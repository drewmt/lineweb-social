<?php

namespace App\Community;

use App\Enums\SpaceAuditAction;
use App\Events\SpaceEventCancelled;
use App\Models\SpaceAuditLog;
use App\Models\SpaceEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CancelSpaceEvent
{
    public function handle(User $actor, SpaceEvent $event): bool
    {
        $cancelledEvent = DB::transaction(function () use ($actor, $event): ?SpaceEvent {
            $lockedEvent = SpaceEvent::query()
                ->with('space')
                ->whereKey($event->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            Gate::forUser($actor)->authorize('cancel', $lockedEvent);

            if ($lockedEvent->isCancelled()) {
                return null;
            }

            $lockedEvent->forceFill([
                'cancelled_at' => now(),
                'cancelled_by' => $actor->getKey(),
            ])->save();

            SpaceAuditLog::query()->create([
                'space_id' => $lockedEvent->space_id,
                'actor_id' => $actor->getKey(),
                'action' => SpaceAuditAction::EventCancelled,
                'context' => ['event_id' => $lockedEvent->getKey()],
            ]);

            return $lockedEvent;
        });

        if (! $cancelledEvent instanceof SpaceEvent) {
            return false;
        }

        SpaceEventCancelled::dispatch($cancelledEvent, $actor);

        return true;
    }
}
