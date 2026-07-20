<?php

namespace App\Http\Controllers;

use App\Community\SpaceMembershipManager;
use App\Enums\SpaceRole;
use App\Http\Requests\StoreSpaceInvitationRequest;
use App\Models\Space;
use App\Models\SpaceInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SpaceInvitationController extends Controller
{
    public function store(
        StoreSpaceInvitationRequest $request,
        Space $space,
        SpaceMembershipManager $memberships,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $role = SpaceRole::from($request->string('role')->toString());
        Gate::authorize('invite', [$space, $role]);

        $memberships->invite(
            $space,
            $actor,
            $request->string('email')->toString(),
            $role,
        );

        return to_route('spaces.manage', $space)
            ->with('status', 'Invitation sent.');
    }

    public function destroy(
        Request $request,
        Space $space,
        SpaceInvitation $invitation,
        SpaceMembershipManager $memberships,
    ): RedirectResponse {
        Gate::authorize('revokeInvitation', [$space, $invitation]);

        /** @var User $actor */
        $actor = $request->user();
        $memberships->revokeInvitation($space, $invitation, $actor);

        return to_route('spaces.manage', $space)
            ->with('status', 'Invitation cancelled.');
    }
}
