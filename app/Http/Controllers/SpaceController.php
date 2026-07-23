<?php

namespace App\Http\Controllers;

use App\Community\CommunityFeed;
use App\Community\CreateSpace;
use App\Enums\PostReactionType;
use App\Enums\ReportReason;
use App\Enums\SpaceVisibility;
use App\Http\Requests\StoreSpaceRequest;
use App\Models\Space;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SpaceController extends Controller
{
    public function index(Request $request, CommunityFeed $feed): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('spaces/index', [
            'spaces' => $feed->spaces($user),
        ]);
    }

    public function store(StoreSpaceRequest $request, CreateSpace $createSpace): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $space = $createSpace->handle(
            $user,
            $request->string('name')->trim()->toString(),
            $request->filled('description')
                ? $request->string('description')->trim()->toString()
                : null,
            SpaceVisibility::from($request->string('visibility')->toString()),
        );

        return to_route('spaces.show', $space)
            ->with('status', 'Your space is ready.');
    }

    public function show(Request $request, Space $space, CommunityFeed $feed): Response
    {
        Gate::authorize('view', $space);

        /** @var User $user */
        $user = $request->user();

        return Inertia::render('feed/index', [
            'spaces' => $feed->spaces($user),
            'posts' => $feed->posts($user, $space),
            'reportReasons' => ReportReason::options(),
            'reactionTypes' => PostReactionType::options(),
            'selectedSpace' => $space->slug,
        ]);
    }
}
