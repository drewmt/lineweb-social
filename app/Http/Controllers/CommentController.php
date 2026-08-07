<?php

namespace App\Http\Controllers;

use App\Community\ManageAuthoredContent;
use App\Community\PublishComment;
use App\Events\CommentPublished;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    public function store(
        StoreCommentRequest $request,
        Post $post,
        PublishComment $comments,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $parentId = $request->filled('parent_id')
            ? $request->integer('parent_id')
            : null;
        $comment = $comments->create(
            $user,
            $post,
            $request->string('body')->toString(),
            $parentId,
        );

        CommentPublished::dispatch($comment);

        return back()->with('status', 'Comment published.');
    }

    public function update(
        UpdateCommentRequest $request,
        Comment $comment,
        ManageAuthoredContent $content,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $changed = $content->updateComment(
            $user,
            $comment,
            $request->string('body')->toString(),
        );

        return back()->with('status', $changed ? 'Comment updated.' : 'No changes to save.');
    }

    public function destroy(
        Request $request,
        Comment $comment,
        ManageAuthoredContent $content,
    ): RedirectResponse {
        Gate::authorize('delete', $comment);
        /** @var User $user */
        $user = $request->user();
        $content->deleteComment($user, $comment);

        return back()->with('status', 'Comment deleted.');
    }
}
