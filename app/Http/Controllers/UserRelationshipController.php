<?php

namespace App\Http\Controllers;

use App\Community\ManageUserSafety;
use App\Enums\UserRelationshipType;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserRelationshipController extends Controller
{
    public function mute(Request $request, User $profile, ManageUserSafety $safety): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $safety->set($actor, $profile, UserRelationshipType::Mute);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('This person is muted.')]);

        return back();
    }

    public function unmute(Request $request, User $profile, ManageUserSafety $safety): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $safety->remove($actor, $profile, UserRelationshipType::Mute);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('This person is no longer muted.')]);

        return back();
    }

    public function block(Request $request, User $profile, ManageUserSafety $safety): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $safety->set($actor, $profile, UserRelationshipType::Block);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('This person is blocked.')]);

        return to_route('people.index');
    }

    public function unblock(Request $request, User $profile, ManageUserSafety $safety): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $safety->remove($actor, $profile, UserRelationshipType::Block);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('This person is no longer blocked.')]);

        return back();
    }
}
