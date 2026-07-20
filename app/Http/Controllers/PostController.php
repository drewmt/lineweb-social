<?php

namespace App\Http\Controllers;

use App\Community\PostConversation;
use App\Enums\ReportReason;
use App\Events\PostPublished;
use App\Http\Requests\StorePostRequest;
use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    public function show(Request $request, Post $post, PostConversation $conversation): Response
    {
        Gate::authorize('view', $post);

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('posts/show', [
            ...$conversation->for($user, $post),
            'reportReasons' => ReportReason::options(),
        ]);
    }

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
