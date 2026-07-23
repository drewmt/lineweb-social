<?php

namespace App\Http\Controllers;

use App\Community\CommunityFeed;
use App\Enums\PostReactionType;
use App\Enums\ReportReason;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SavedPostController extends Controller
{
    public function index(Request $request, CommunityFeed $feed): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('feed/index', [
            'spaces' => $feed->spaces($user),
            'posts' => $feed->posts($user, savedOnly: true),
            'reportReasons' => ReportReason::options(),
            'reactionTypes' => PostReactionType::options(),
            'selectedSpace' => null,
            'viewMode' => 'saved',
        ]);
    }

    public function store(Request $request, Post $post): RedirectResponse
    {
        Gate::authorize('save', $post);

        DB::table('post_saves')->insertOrIgnore([
            'user_id' => $request->user()->getAuthIdentifier(),
            'post_id' => $post->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Post saved for later.');
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        DB::table('post_saves')
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->where('post_id', $post->getKey())
            ->delete();

        return back()->with('status', 'Post removed from saved.');
    }
}
