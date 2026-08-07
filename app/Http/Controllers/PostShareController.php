<?php

namespace App\Http\Controllers;

use App\Community\SharePost;
use App\Http\Requests\StorePostShareRequest;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class PostShareController extends Controller
{
    public function store(
        StorePostShareRequest $request,
        Post $post,
        SharePost $shares,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $sharedPost = $shares->share(
            $user,
            $post,
            $request->string('body')->toString(),
        );

        return back()->with(
            'status',
            $sharedPost->body === '' ? 'Post reposted.' : 'Quote post published.',
        );
    }
}
