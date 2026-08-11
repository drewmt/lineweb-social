<?php

namespace App\Http\Controllers\Api\V1;

use App\Community\SpaceEventProjection;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SpaceEventResource;
use App\Models\SpaceEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SpaceEventController extends Controller
{
    public function __invoke(
        Request $request,
        SpaceEvent $spaceEvent,
        SpaceEventProjection $events,
    ): SpaceEventResource {
        $spaceEvent->loadMissing('space');
        Gate::authorize('view', $spaceEvent);

        /** @var User $viewer */
        $viewer = $request->user();
        $hydrated = $events->query($viewer, $spaceEvent->space)
            ->whereKey($spaceEvent->getKey())
            ->firstOrFail();

        return new SpaceEventResource($hydrated);
    }
}
