<?php

namespace App\Http\Controllers;

use App\Community\Polls\VoteOnPostPoll;
use App\Http\Requests\StorePostPollVoteRequest;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class PostPollVoteController extends Controller
{
    public function store(
        StorePostPollVoteRequest $request,
        Post $post,
        VoteOnPostPoll $votes,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $result = $votes->cast(
            $user,
            $post,
            $request->integer('option_id'),
        );

        return back()->with(
            'status',
            ! $result['changed']
                ? 'Vote unchanged.'
                : ($result['previousOptionId'] === null
                    ? 'Vote recorded.'
                    : 'Vote updated.'),
        );
    }
}
