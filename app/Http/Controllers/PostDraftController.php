<?php

namespace App\Http\Controllers;

use App\Community\ManagePostDrafts;
use App\Community\PostMediaView;
use App\Http\Requests\SavePostDraftRequest;
use App\Models\Post;
use App\Models\PostPoll;
use App\Models\PostPollOption;
use App\Models\PostVideo;
use App\Models\Space;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PostDraftController extends Controller
{
    public function index(Request $request, PostMediaView $media): Response
    {
        /** @var User $user */
        $user = $request->user();
        $drafts = $user->posts()
            ->whereNull('published_at')
            ->whereNull('hidden_at')
            ->with(['space:id,name,slug,visibility', 'media', 'mediaItems', 'poll.options', 'video'])
            ->latest('updated_at')
            ->latest('id')
            ->limit(ManagePostDrafts::MAX_DRAFTS_PER_MEMBER)
            ->get();

        return Inertia::render('drafts/index', [
            'drafts' => $drafts
                ->map(fn (Post $draft): array => $this->draftView($draft, $media))
                ->values()
                ->all(),
            'limit' => ManagePostDrafts::MAX_DRAFTS_PER_MEMBER,
            'videoEnabled' => config('media.video.enabled') === true,
        ]);
    }

    public function create(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $spaces = $this->postingSpaces($user);
        $requestedSpace = $request->string('space')->trim()->toString();
        $selectedSpace = $spaces->contains('slug', $requestedSpace)
            ? $requestedSpace
            : $spaces->first()?->slug;

        return Inertia::render('compose/index', [
            'spaces' => $this->spaceViews($spaces),
            'selectedSpace' => $selectedSpace,
            'draft' => null,
            'videoEnabled' => config('media.video.enabled') === true,
            'suggestedMode' => $request->query('mode') === 'video' ? 'video' : null,
        ]);
    }

    public function store(
        SavePostDraftRequest $request,
        ManagePostDrafts $drafts,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $space = $this->validatedSpace($request);
        $draft = $drafts->create(
            $user,
            $space,
            $request->string('body')->toString(),
            $request->galleryUploads(),
            $request->galleryAltTexts(),
            $request->pollDefinition(),
        );

        return to_route('drafts.edit', [
            'post' => $draft,
            ...($request->input('intent') === 'video' ? ['mode' => 'video'] : []),
        ])->with('status', 'Draft saved privately.');
    }

    public function edit(
        Request $request,
        Post $post,
        PostMediaView $media,
    ): Response {
        Gate::authorize('manageDraft', $post);
        /** @var User $user */
        $user = $request->user();
        $spaces = $this->postingSpaces($user);
        $selectedSpace = $spaces->contains('slug', $post->space->slug)
            ? $post->space->slug
            : $spaces->first()?->slug;

        return Inertia::render('compose/index', [
            'spaces' => $this->spaceViews($spaces),
            'selectedSpace' => $selectedSpace,
            'draft' => $this->draftView($post->load(['media', 'mediaItems', 'poll.options', 'video']), $media),
            'videoEnabled' => config('media.video.enabled') === true,
            'suggestedMode' => $request->query('mode') === 'video' ? 'video' : null,
        ]);
    }

    public function update(
        SavePostDraftRequest $request,
        Post $post,
        ManagePostDrafts $drafts,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $drafts->update(
            $user,
            $post,
            $this->validatedSpace($request),
            $request->string('body')->toString(),
            $request->galleryUploads(),
            $request->galleryAltTexts(),
            $request->retainedMediaAltTexts($post),
            $request->pollDefinition(),
        );

        return back()->with('status', 'Draft updated privately.');
    }

    public function publish(
        SavePostDraftRequest $request,
        Post $post,
        ManagePostDrafts $drafts,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $published = $drafts->publish(
            $user,
            $post,
            $this->validatedSpace($request),
            $request->string('body')->toString(),
            $request->galleryUploads(),
            $request->galleryAltTexts(),
            $request->retainedMediaAltTexts($post),
            $request->pollDefinition(),
        );

        return to_route('posts.show', $published)->with('status', 'Post published.');
    }

    public function destroy(
        Request $request,
        Post $post,
        ManagePostDrafts $drafts,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $drafts->delete($user, $post);

        return to_route('drafts.index')->with('status', 'Draft deleted.');
    }

    /** @return Collection<int, Space> */
    private function postingSpaces(User $user): Collection
    {
        return $user->spaces()
            ->select(['spaces.id', 'spaces.name', 'spaces.slug', 'spaces.visibility'])
            ->orderBy('spaces.name')
            ->get();
    }

    /**
     * @param  Collection<int, Space>  $spaces
     * @return list<array{name: string, slug: string, visibility: string}>
     */
    private function spaceViews(Collection $spaces): array
    {
        return array_values(
            $spaces->map(fn (Space $space): array => [
                'name' => $space->name,
                'slug' => $space->slug,
                'visibility' => $space->visibility->value,
            ])
                ->all(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function draftView(Post $draft, PostMediaView $media): array
    {
        $poll = $draft->poll;
        $draft->loadMissing('video');
        $video = $draft->video;

        return [
            'id' => $draft->id,
            'body' => $draft->body,
            'updatedAt' => $draft->updated_at?->toIso8601String() ?? now()->toIso8601String(),
            'editUrl' => route('drafts.edit', $draft),
            'space' => [
                'name' => $draft->space->name,
                'slug' => $draft->space->slug,
            ],
            'media' => $media->for($draft),
            'mediaItems' => $media->galleryFor($draft),
            'video' => $video instanceof PostVideo ? [
                'status' => $video->status,
                'description' => $video->description,
                'url' => $video->status === PostVideo::STATUS_READY
                    ? route('posts.video', $draft)
                    : null,
                'posterUrl' => $video->status === PostVideo::STATUS_READY
                    ? route('posts.video.poster', $draft)
                    : null,
            ] : null,
            'poll' => ! $poll instanceof PostPoll ? null : [
                'question' => $poll->question,
                'options' => array_values(array_map(
                    static fn (PostPollOption $option): string => $option->label,
                    $poll->options->all(),
                )),
                'duration' => $poll->closes_after_days,
            ],
        ];
    }

    private function validatedSpace(SavePostDraftRequest $request): Space
    {
        return Space::query()
            ->where('slug', $request->string('space')->toString())
            ->firstOrFail();
    }
}
