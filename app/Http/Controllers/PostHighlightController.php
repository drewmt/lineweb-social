<?php

namespace App\Http\Controllers;

use App\Community\ManageSpaceHighlights;
use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PostHighlightController extends Controller
{
    public function store(
        Request $request,
        Space $space,
        Post $post,
        ManageSpaceHighlights $highlights,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $changed = $highlights->highlight($actor, $space, $post);

        return back()->with(
            'status',
            $changed ? 'Post added to Space highlights.' : 'Post is already highlighted.',
        );
    }

    public function destroy(
        Request $request,
        Space $space,
        Post $post,
        ManageSpaceHighlights $highlights,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $changed = $highlights->remove($actor, $space, $post);

        return back()->with(
            'status',
            $changed ? 'Post removed from Space highlights.' : 'Post was not highlighted.',
        );
    }
}
