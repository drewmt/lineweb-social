<?php

namespace App\Http\Controllers;

use App\Events\CommentPublished;
use App\Http\Requests\StoreCommentRequest;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;

class CommentController extends Controller
{
    public function store(StoreCommentRequest $request, Post $post): RedirectResponse
    {
        $comment = $post->comments()->create([
            'user_id' => $request->user()->getKey(),
            'body' => $request->string('body')->toString(),
            'published_at' => now(),
        ]);

        CommentPublished::dispatch($comment);

        return back()->with('status', 'Comment published.');
    }
}
