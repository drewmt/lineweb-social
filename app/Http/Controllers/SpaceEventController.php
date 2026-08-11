<?php

namespace App\Http\Controllers;

use App\Community\CancelSpaceEvent;
use App\Community\CommunityFeed;
use App\Community\CreateSpaceEvent;
use App\Community\SpaceEventProjection;
use App\Http\Requests\StoreSpaceEventRequest;
use App\Models\Space;
use App\Models\SpaceEvent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SpaceEventController extends Controller
{
    public function index(
        Request $request,
        Space $space,
        CommunityFeed $feed,
        SpaceEventProjection $events,
    ): Response {
        Gate::authorize('view', $space);

        /** @var User $viewer */
        $viewer = $request->user();
        $spaces = $feed->spaces($viewer);

        return Inertia::render('events/index', [
            'space' => collect($spaces)->firstWhere('slug', $space->slug),
            'upcomingEvents' => $events->upcoming($viewer, $space),
            'pastEvents' => $events->past($viewer, $space),
        ]);
    }

    public function store(
        StoreSpaceEventRequest $request,
        Space $space,
        CreateSpaceEvent $createEvent,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $event = $createEvent->handle($actor, $space, $request->eventAttributes());

        return to_route('events.show', $event)
            ->with('status', 'Event published.');
    }

    public function show(
        Request $request,
        SpaceEvent $spaceEvent,
        SpaceEventProjection $events,
    ): Response {
        $spaceEvent->loadMissing('space');
        Gate::authorize('view', $spaceEvent);

        /** @var User $viewer */
        $viewer = $request->user();

        return Inertia::render('events/show', [
            'event' => $events->one($viewer, $spaceEvent),
        ]);
    }

    public function cancel(
        Request $request,
        SpaceEvent $spaceEvent,
        CancelSpaceEvent $cancelEvent,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $changed = $cancelEvent->handle($actor, $spaceEvent);

        return back()->with(
            'status',
            $changed ? 'Event cancelled.' : 'Event was already cancelled.',
        );
    }
}
