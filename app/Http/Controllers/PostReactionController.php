<?php

namespace App\Http\Controllers;

use App\Community\ReactToPost;
use App\Enums\PostReactionType;
use App\Http\Requests\StorePostReactionRequest;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PostReactionController extends Controller
{
    public function store(
        StorePostReactionRequest $request,
        Post $post,
        ReactToPost $reactions,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $result = $reactions->set(
            $user,
            $post,
            PostReactionType::from($request->string('type')->toString()),
        );
        $status = ! $result['changed']
            ? 'Reaction unchanged.'
            : ($result['previousType'] instanceof PostReactionType
                ? 'Reaction updated.'
                : 'Reaction added.');

        return back()->with('status', $status);
    }

    public function destroy(
        Request $request,
        string $post,
        ReactToPost $reactions,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $removed = $reactions->remove($user, $post);

        return back()->with(
            'status',
            $removed ? 'Reaction removed.' : 'No reaction to remove.',
        );
    }
}
