<?php

namespace App\Community;

use App\Events\ProfilePostHighlightChanged;
use App\Models\Post;
use App\Models\ProfilePostHighlight;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ManageProfileHighlights
{
    public const MAX_HIGHLIGHTS = 3;

    public function highlight(User $actor, User $profile, Post $post): bool
    {
        $result = DB::transaction(function () use ($actor, $profile, $post): array {
            $lockedProfile = User::query()
                ->whereKey($profile->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedPost = Post::query()
                ->with(['author', 'space', 'poll'])
                ->whereKey($post->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureSameAuthor($lockedProfile, $lockedPost);
            Gate::forUser($actor)->authorize('pinToProfile', $lockedPost);

            $existing = ProfilePostHighlight::query()
                ->whereBelongsTo($lockedPost)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof ProfilePostHighlight) {
                return ['changed' => false, 'post' => $lockedPost];
            }

            $this->removeStaleHighlights($lockedProfile);

            if ($lockedProfile->profileHighlights()->count() >= self::MAX_HIGHLIGHTS) {
                throw ValidationException::withMessages([
                    'highlight' => 'Your profile can feature up to three posts. Remove one before adding another.',
                ]);
            }

            ProfilePostHighlight::query()->create([
                'user_id' => $lockedProfile->getKey(),
                'post_id' => $lockedPost->getKey(),
            ]);

            return ['changed' => true, 'post' => $lockedPost];
        });

        if ($result['changed']) {
            ProfilePostHighlightChanged::dispatch($result['post'], $actor, true);
        }

        return $result['changed'];
    }

    public function remove(User $actor, User $profile, Post $post): bool
    {
        $result = DB::transaction(function () use ($actor, $profile, $post): array {
            $lockedProfile = User::query()
                ->whereKey($profile->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedPost = Post::query()
                ->with(['author', 'space', 'poll'])
                ->whereKey($post->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureSameAuthor($lockedProfile, $lockedPost);
            Gate::forUser($actor)->authorize('removeProfileHighlight', $lockedPost);

            $highlight = ProfilePostHighlight::query()
                ->whereBelongsTo($lockedPost)
                ->lockForUpdate()
                ->first();

            if (! $highlight instanceof ProfilePostHighlight) {
                return ['changed' => false, 'post' => $lockedPost];
            }

            $highlight->delete();

            return ['changed' => true, 'post' => $lockedPost];
        });

        if ($result['changed']) {
            ProfilePostHighlightChanged::dispatch($result['post'], $actor, false);
        }

        return $result['changed'];
    }

    private function ensureSameAuthor(User $profile, Post $post): void
    {
        if ($post->user_id !== $profile->getKey()) {
            throw new NotFoundHttpException;
        }
    }

    private function removeStaleHighlights(User $profile): void
    {
        $profile->profileHighlights()
            ->with(['post.author', 'post.space', 'post.poll'])
            ->lockForUpdate()
            ->get()
            ->each(function (ProfilePostHighlight $highlight) use ($profile): void {
                if (! Gate::forUser($profile)->allows('pinToProfile', $highlight->post)) {
                    $highlight->delete();
                }
            });
    }
}
