<?php

namespace App\Http\Controllers;

use App\Community\SpaceMembershipManager;
use App\Enums\SpaceRole;
use App\Http\Requests\RemoveSpaceMemberRequest;
use App\Http\Requests\TransferSpaceOwnershipRequest;
use App\Http\Requests\UpdateSpaceMemberRoleRequest;
use App\Models\Space;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class SpaceMemberController extends Controller
{
    public function update(
        UpdateSpaceMemberRoleRequest $request,
        Space $space,
        User $member,
        SpaceMembershipManager $memberships,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $memberships->updateRole(
            $space,
            $member,
            $actor,
            SpaceRole::from($request->string('role')->toString()),
        );

        return to_route('spaces.manage', $space)
            ->with('status', "{$member->name}'s role was updated.");
    }

    public function destroy(
        RemoveSpaceMemberRequest $request,
        Space $space,
        User $member,
        SpaceMembershipManager $memberships,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $memberships->removeMember(
            $space,
            $member,
            $actor,
            $request->string('reason')->toString(),
        );

        return to_route('spaces.manage', $space)
            ->with('status', "{$member->name} was removed from the space.");
    }

    public function transferOwnership(
        TransferSpaceOwnershipRequest $request,
        Space $space,
        SpaceMembershipManager $memberships,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $newOwner = User::query()->findOrFail($request->integer('member_id'));
        Gate::authorize('transferOwnership', $space);
        $memberships->transferOwnership($space, $newOwner, $actor);

        return to_route('spaces.manage', $space)
            ->with('status', "Ownership was transferred to {$newOwner->name}.");
    }
}
