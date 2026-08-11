<?php

namespace App\Community;

use App\Enums\SpaceAuditAction;
use App\Events\SpaceEventPublished;
use App\Models\Space;
use App\Models\SpaceAuditLog;
use App\Models\SpaceEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CreateSpaceEvent
{
    /**
     * @param  array{title: string, description: string|null, starts_at: CarbonImmutable, ends_at: CarbonImmutable, timezone: string, venue: string|null, online_url: string|null, capacity: int|null}  $attributes
     */
    public function handle(User $actor, Space $space, array $attributes): SpaceEvent
    {
        $event = DB::transaction(function () use ($actor, $space, $attributes): SpaceEvent {
            $lockedSpace = Space::query()
                ->whereKey($space->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            Gate::forUser($actor)->authorize('create', [SpaceEvent::class, $lockedSpace]);

            $event = $lockedSpace->events()->create([
                ...$attributes,
                'created_by' => $actor->getKey(),
            ]);

            SpaceAuditLog::query()->create([
                'space_id' => $lockedSpace->getKey(),
                'actor_id' => $actor->getKey(),
                'action' => SpaceAuditAction::EventCreated,
                'context' => ['event_id' => $event->getKey()],
            ]);

            return $event;
        });

        SpaceEventPublished::dispatch($event, $actor);

        return $event;
    }
}
