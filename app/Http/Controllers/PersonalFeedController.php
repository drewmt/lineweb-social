<?php

namespace App\Http\Controllers;

use App\Community\CommunityFeed;
use App\Community\PersonalFeedSources;
use App\Enums\PostReactionType;
use App\Enums\ReportReason;
use App\Models\PersonalFeed;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PersonalFeedController extends Controller
{
    public function index(Request $request): Response
    {
        $feeds = PersonalFeed::query()->ownedBy($this->viewer($request))->orderBy('id')
            ->get()->map($this->summary(...));

        return Inertia::render('personal-feeds/index', [
            'feeds' => $feeds,
            'maxFeeds' => PersonalFeedSources::MAX_FEEDS,
        ]);
    }

    public function create(): Response
    {
        return $this->editor();
    }

    public function sources(Request $request, PersonalFeedSources $sources): JsonResponse
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:80']]);

        return response()->json($sources->search($this->viewer($request), (string) ($validated['q'] ?? '')))
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, PersonalFeedSources $sources): RedirectResponse
    {
        $viewer = $this->viewer($request);
        $data = $this->validateFeed($request);
        $feed = DB::transaction(function () use ($viewer, $data, $sources): PersonalFeed {
            User::query()->whereKey($viewer->id)->lockForUpdate()->firstOrFail();
            if (PersonalFeed::query()->ownedBy($viewer)->count() >= PersonalFeedSources::MAX_FEEDS) {
                throw ValidationException::withMessages(['name' => 'You have reached the limit of '.PersonalFeedSources::MAX_FEEDS.' personal feeds.']);
            }

            $feed = PersonalFeed::query()->create(['user_id' => $viewer->id, 'name' => $data['name']]);
            $sources->sync($viewer, $feed, $data['sources']);

            return $feed;
        });

        return to_route('personal-feeds.show', $feed);
    }

    public function show(Request $request, int $personalFeed, CommunityFeed $community, PersonalFeedSources $sources): Response
    {
        $viewer = $this->viewer($request);
        $feed = PersonalFeed::query()->ownedBy($viewer)->findOrFail($personalFeed);
        $selected = $sources->selected($viewer, $feed);

        return Inertia::render('personal-feeds/show', [
            'feed' => $this->summary($feed),
            'sourceCount' => array_sum(array_map(count(...), $selected)),
            'posts' => $community->posts($viewer, personalSources: [
                'people' => array_column($selected['people'], 'id'),
                'spaces' => array_column($selected['spaces'], 'id'),
                'topics' => array_column($selected['topics'], 'id'),
            ]),
            'reportReasons' => ReportReason::options(),
            'reactionTypes' => PostReactionType::options(),
        ]);
    }

    public function edit(Request $request, int $personalFeed, PersonalFeedSources $sources): Response
    {
        $viewer = $this->viewer($request);
        $feed = PersonalFeed::query()->ownedBy($viewer)->findOrFail($personalFeed);

        return $this->editor($feed, $sources->selected($viewer, $feed));
    }

    public function update(Request $request, int $personalFeed, PersonalFeedSources $sources): RedirectResponse
    {
        $viewer = $this->viewer($request);
        $feed = DB::transaction(function () use ($viewer, $personalFeed, $sources, $request): PersonalFeed {
            $feed = PersonalFeed::query()->ownedBy($viewer)->lockForUpdate()->findOrFail($personalFeed);
            $data = $this->validateFeed($request);
            $sources->sync($viewer, $feed, $data['sources']);
            $feed->update(['name' => $data['name']]);

            return $feed;
        });

        return to_route('personal-feeds.show', $feed);
    }

    public function destroy(Request $request, int $personalFeed): RedirectResponse
    {
        PersonalFeed::query()->ownedBy($this->viewer($request))->findOrFail($personalFeed)->delete();

        return to_route('personal-feeds.index');
    }

    /**
     * @param  array{people: list<array{id: int, label: string, reference: string}>, spaces: list<array{id: int, label: string, reference: string}>, topics: list<array{id: int, label: string, reference: string}>}  $selected
     */
    private function editor(?PersonalFeed $feed = null, array $selected = ['people' => [], 'spaces' => [], 'topics' => []]): Response
    {
        return Inertia::render('personal-feeds/form', [
            'feed' => $feed === null ? null : $this->summary($feed),
            'selectedSources' => $selected,
            'maxSources' => PersonalFeedSources::MAX_SOURCES,
        ]);
    }

    /** @return array{name: string, sources: array{people: list<int>, spaces: list<int>, topics: list<int>}} */
    private function validateFeed(Request $request): array
    {
        $rules = ['name' => ['bail', 'required', 'string', 'max:80'], 'sources' => ['present', 'array:people,spaces,topics']];
        foreach (['people', 'spaces', 'topics'] as $type) {
            $rules['sources.'.$type] = ['bail', 'array', 'list', 'max:'.PersonalFeedSources::MAX_SOURCES];
            $rules['sources.'.$type.'.*'] = ['bail', 'integer', 'min:1', 'distinct'];
        }
        $validated = $request->validate($rules);

        return ['name' => $validated['name'], 'sources' => [
            'people' => array_values(array_map(intval(...), $validated['sources']['people'] ?? [])),
            'spaces' => array_values(array_map(intval(...), $validated['sources']['spaces'] ?? [])),
            'topics' => array_values(array_map(intval(...), $validated['sources']['topics'] ?? [])),
        ]];
    }

    /** @return array{id: int, name: string, url: string, editUrl: string} */
    private function summary(PersonalFeed $feed): array
    {
        return [
            'id' => $feed->id,
            'name' => $feed->name,
            'url' => route('personal-feeds.show', $feed),
            'editUrl' => route('personal-feeds.edit', $feed),
        ];
    }

    private function viewer(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
