<?php

namespace App\Http\Controllers;

use App\Enums\SpaceRole;
use App\Models\Space;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SpaceMembershipController extends Controller
{
    public function store(Request $request, Space $space): RedirectResponse
    {
        Gate::authorize('join', $space);

        /** @var User $user */
        $user = $request->user();
        $space->addMember($user, SpaceRole::Member);

        return to_route('spaces.show', $space)
            ->with('status', "You're now a member of {$space->name}.");
    }

    public function destroy(Request $request, Space $space): RedirectResponse
    {
        Gate::authorize('leave', $space);

        $space->members()->detach($request->user()->getKey());

        return to_route('spaces.index')
            ->with('status', "You left {$space->name}.");
    }
}
