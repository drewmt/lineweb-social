<?php

namespace App\Http\Controllers;

use App\Community\ManageProfileHighlights;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfilePostHighlightController extends Controller
{
    public function store(
        Request $request,
        User $profile,
        Post $post,
        ManageProfileHighlights $highlights,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $changed = $highlights->highlight($actor, $profile, $post);

        return back()->with(
            'status',
            $changed ? 'Post pinned to your profile.' : 'Post is already pinned.',
        );
    }

    public function destroy(
        Request $request,
        User $profile,
        Post $post,
        ManageProfileHighlights $highlights,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $changed = $highlights->remove($actor, $profile, $post);

        return back()->with(
            'status',
            $changed ? 'Post removed from your profile highlights.' : 'Post was not pinned.',
        );
    }
}
