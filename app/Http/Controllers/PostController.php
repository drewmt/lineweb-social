<?php

namespace App\Http\Controllers;

use App\Community\PostConversation;
use App\Community\PublishPost;
use App\Enums\ReportReason;
use App\Http\Requests\StorePostRequest;
use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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

    public function store(
        StorePostRequest $request,
        Space $space,
        PublishPost $publisher,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $upload = $request->file('image');

        $publisher->publish(
            $user,
            $space,
            $request->string('body')->toString(),
            $upload instanceof UploadedFile ? $upload : null,
            $request->filled('image_alt')
                ? $request->string('image_alt')->toString()
                : null,
        );

        return back()->with('status', 'Post published.');
    }
}
