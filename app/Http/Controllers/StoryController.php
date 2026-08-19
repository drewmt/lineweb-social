<?php

namespace App\Http\Controllers;

use App\Community\DeleteStory;
use App\Community\PublishStory;
use App\Community\StoryProjection;
use App\Http\Requests\StoreStoryRequest;
use App\Models\Space;
use App\Models\Story;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StoryController extends Controller
{
    public function create(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('stories/create', [
            'spaces' => $user->spaces()
                ->select(['spaces.id', 'spaces.name', 'spaces.slug'])
                ->orderBy('spaces.name')
                ->get()
                ->map(fn (Space $space): array => [
                    'name' => $space->name,
                    'slug' => $space->slug,
                ])
                ->values()
                ->all(),
            'backgrounds' => Story::BACKGROUNDS,
            'activeLimit' => Story::ACTIVE_LIMIT_PER_SPACE,
            'lifetimeHours' => Story::LIFETIME_HOURS,
        ]);
    }

    public function store(
        StoreStoryRequest $request,
        Space $space,
        PublishStory $publisher,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $story = $publisher->publish(
            $user,
            $space,
            $request->string('body')->toString(),
            $request->string('background')->toString(),
            $request->file('image'),
            $request->string('alt_text')->toString(),
        );

        return redirect()->route('stories.show', $story)->with('status', 'Story published for 24 hours.');
    }

    public function show(Request $request, Story $story, StoryProjection $stories): Response
    {
        abort_if($story->expires_at->isPast(), 404);
        Gate::authorize('view', $story);
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('stories/show', [
            'story' => $stories->one($user, $story),
        ]);
    }

    public function destroy(Request $request, Story $story, DeleteStory $deleteStory): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $deleteStory->handle($user, $story);

        return redirect()->route('feed')->with('status', 'Story removed.');
    }
}
