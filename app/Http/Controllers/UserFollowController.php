<?php

namespace App\Http\Controllers;

use App\Community\ManageUserFollows;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserFollowController extends Controller
{
    public function store(
        Request $request,
        User $profile,
        ManageUserFollows $follows,
    ): RedirectResponse {
        /** @var User $viewer */
        $viewer = $request->user();
        $changed = $follows->follow($viewer, $profile);

        return back()->with(
            'status',
            $changed
                ? "You're now following {$profile->name}."
                : "You're already following {$profile->name}.",
        );
    }

    public function destroy(
        Request $request,
        User $profile,
        ManageUserFollows $follows,
    ): RedirectResponse {
        /** @var User $viewer */
        $viewer = $request->user();
        $changed = $follows->unfollow($viewer, $profile);

        return back()->with(
            'status',
            $changed
                ? "You unfollowed {$profile->name}."
                : "You're not following {$profile->name}.",
        );
    }
}
