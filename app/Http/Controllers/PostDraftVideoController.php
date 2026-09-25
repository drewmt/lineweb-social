<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostVideoRequest;
use App\Media\VideoUpload;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PostDraftVideoController extends Controller
{
    public function store(StorePostVideoRequest $request, Post $post, VideoUpload $videos): RedirectResponse
    {
        abort_unless(config('media.video.enabled') === true, 404);

        /** @var User $author */
        $author = $request->user();
        $file = $request->file('video');
        abort_if($file === null, 422);

        $videos->attach($author, $post, $file, $request->string('description')->toString());

        return back()->with('status', 'Video uploaded privately and queued for processing.');
    }

    public function destroy(Request $request, Post $post, VideoUpload $videos): RedirectResponse
    {
        abort_unless(config('media.video.enabled') === true, 404);
        Gate::authorize('manageDraft', $post);

        /** @var User $author */
        $author = $request->user();
        $videos->remove($author, $post);

        return back()->with('status', 'Draft video removed.');
    }
}
