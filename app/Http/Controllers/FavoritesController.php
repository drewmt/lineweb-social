<?php

namespace App\Http\Controllers;

use App\Community\CommunityFeed;
use App\Community\FavoriteSources;
use App\Enums\PostReactionType;
use App\Enums\ReportReason;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FavoritesController extends Controller
{
    public function index(Request $request, CommunityFeed $feed): Response
    {
        /** @var User $viewer */
        $viewer = $request->user();

        return Inertia::render('feed/index', [
            'spaces' => $feed->spaces($viewer),
            'posts' => $feed->posts($viewer, favoritesOnly: true),
            'reportReasons' => ReportReason::options(),
            'reactionTypes' => PostReactionType::options(),
            'selectedSpace' => null,
            'viewMode' => 'favorites',
        ]);
    }

    public function profile(Request $request, User $profile, FavoriteSources $favorites): RedirectResponse
    {
        Gate::authorize('favorite', $profile);

        /** @var User $viewer */
        $viewer = $request->user();
        $this->update($request, $favorites->profiles($viewer), $viewer->getKey(), 'profile_id', $profile->getKey());

        return to_route('people.show', $profile);
    }

    public function space(Request $request, Space $space, FavoriteSources $favorites): RedirectResponse
    {
        Gate::authorize('view', $space);

        /** @var User $viewer */
        $viewer = $request->user();
        $this->update($request, $favorites->spaces($viewer), $viewer->getKey(), 'space_id', $space->getKey());

        return to_route('spaces.show', $space);
    }

    private function update(Request $request, Builder $favorites, int $viewerId, string $column, int $targetId): void
    {
        if ($request->isMethod('DELETE')) {
            $favorites->where($column, $targetId)->delete();

            return;
        }

        $favorites->insertOrIgnore([
            'user_id' => $viewerId,
            $column => $targetId,
            'created_at' => now(),
        ]);
    }
}
