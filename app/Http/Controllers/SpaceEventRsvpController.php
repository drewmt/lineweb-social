<?php

namespace App\Http\Controllers;

use App\Community\ManageSpaceEventRsvp;
use App\Enums\SpaceEventRsvpStatus;
use App\Http\Requests\StoreSpaceEventRsvpRequest;
use App\Models\SpaceEvent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SpaceEventRsvpController extends Controller
{
    public function store(
        StoreSpaceEventRsvpRequest $request,
        SpaceEvent $spaceEvent,
        ManageSpaceEventRsvp $rsvps,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $status = SpaceEventRsvpStatus::from($request->string('status')->toString());
        $result = $rsvps->store($actor, $spaceEvent, $status);

        return back()->with(
            'status',
            $result['changed'] ? 'Your RSVP is updated.' : 'Your RSVP is already up to date.',
        );
    }

    public function destroy(
        Request $request,
        SpaceEvent $spaceEvent,
        ManageSpaceEventRsvp $rsvps,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $changed = $rsvps->remove($actor, $spaceEvent);

        return back()->with(
            'status',
            $changed ? 'Your RSVP was removed.' : 'You do not have an RSVP for this event.',
        );
    }
}
