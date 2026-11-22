<?php

namespace App\Http\Controllers;

use App\Community\CommunityFeed;
use App\Community\SavedCollections;
use App\Enums\PostReactionType;
use App\Enums\ReportReason;
use App\Models\Post;
use App\Models\SavedCollection;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SavedPostController extends Controller
{
    public function index(Request $request, CommunityFeed $feed, SavedCollections $collections): Response
    {
        /** @var User $user */
        $user = $request->user();

        return $this->library($user, $feed, $collections);
    }

    public function showCollection(Request $request, string $collection, CommunityFeed $feed, SavedCollections $collections): Response
    {
        /** @var User $user */
        $user = $request->user();

        return $this->library($user, $feed, $collections, $collections->find($user, $collection));
    }

    private function library(User $user, CommunityFeed $feed, SavedCollections $collections, ?SavedCollection $collection = null): Response
    {
        $saved = $feed->savedPage($user, $collection?->id);
        $memberships = $collections->memberships($user, array_column($saved['posts'], 'id'));

        return Inertia::render('feed/index', [
            'spaces' => $feed->spaces($user),
            'posts' => array_map(
                fn (array $post): array => $post + ['savedCollectionIds' => $memberships[$post['id']] ?? []],
                $saved['posts'],
            ),
            'reportReasons' => ReportReason::options(),
            'reactionTypes' => PostReactionType::options(),
            'selectedSpace' => null,
            'viewMode' => 'saved',
            'savedLibrary' => [
                'collections' => $collections->ownedBy($user)->orderBy('name')->orderBy('id')->get(['id', 'name']),
                'collectionLimit' => SavedCollections::LIMIT,
                'activeCollection' => $collection === null ? null : ['id' => $collection->id, 'name' => $collection->name],
                'pagination' => $saved['pagination'],
            ],
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
