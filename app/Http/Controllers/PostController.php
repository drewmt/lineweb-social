<?php

namespace App\Http\Controllers;

use App\Events\PostPublished;
use App\Http\Requests\StorePostRequest;
use App\Models\Space;
use Illuminate\Http\RedirectResponse;

class PostController extends Controller
{
    public function store(StorePostRequest $request, Space $space): RedirectResponse
    {
        $post = $space->posts()->create([
            'user_id' => $request->user()->getKey(),
            'body' => trim($request->validated('body')),
            'published_at' => now(),
        ]);

        PostPublished::dispatch($post);

        return back()->with('status', 'Post published.');
    }
}
