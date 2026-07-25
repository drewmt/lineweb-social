<?php

namespace App\Community;

use App\Enums\SpaceAuditAction;
use App\Events\PostHighlightChanged;
use App\Models\Post;
use App\Models\Space;
use App\Models\SpaceAuditLog;
use App\Models\SpacePostHighlight;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ManageSpaceHighlights
{
    public const MAX_HIGHLIGHTS = 3;

    public function highlight(User $actor, Space $space, Post $post): bool
    {
        $result = DB::transaction(function () use ($actor, $space, $post): array {
            $lockedSpace = Space::query()
                ->whereKey($space->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedPost = Post::query()
                ->with('space')
                ->whereKey($post->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureSameSpace($lockedSpace, $lockedPost);
            Gate::forUser($actor)->authorize('highlight', $lockedPost);

            $existing = SpacePostHighlight::query()
                ->whereBelongsTo($lockedPost)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof SpacePostHighlight) {
                return ['changed' => false, 'post' => $lockedPost];
            }

            if ($lockedSpace->highlights()->count() >= self::MAX_HIGHLIGHTS) {
                throw ValidationException::withMessages([
                    'highlight' => 'A Space can feature up to three highlights. Remove one before adding another.',
                ]);
            }

            SpacePostHighlight::query()->create([
                'space_id' => $lockedSpace->getKey(),
                'post_id' => $lockedPost->getKey(),
                'highlighted_by' => $actor->getKey(),
            ]);
            $this->audit(
                $lockedSpace,
                $lockedPost,
                $actor,
                SpaceAuditAction::PostHighlighted,
            );

            return ['changed' => true, 'post' => $lockedPost];
        });

        if ($result['changed']) {
            PostHighlightChanged::dispatch($result['post'], $actor, true);
        }

        return $result['changed'];
    }

    public function remove(User $actor, Space $space, Post $post): bool
    {
        $result = DB::transaction(function () use ($actor, $space, $post): array {
            $lockedSpace = Space::query()
                ->whereKey($space->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedPost = Post::query()
                ->with('space')
                ->whereKey($post->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureSameSpace($lockedSpace, $lockedPost);
            Gate::forUser($actor)->authorize('removeHighlight', $lockedPost);

            $highlight = SpacePostHighlight::query()
                ->whereBelongsTo($lockedPost)
                ->lockForUpdate()
                ->first();

            if (! $highlight instanceof SpacePostHighlight) {
                return ['changed' => false, 'post' => $lockedPost];
            }

            $highlight->delete();
            $this->audit(
                $lockedSpace,
                $lockedPost,
                $actor,
                SpaceAuditAction::PostUnhighlighted,
            );

            return ['changed' => true, 'post' => $lockedPost];
        });

        if ($result['changed']) {
            PostHighlightChanged::dispatch($result['post'], $actor, false);
        }

        return $result['changed'];
    }

    private function ensureSameSpace(Space $space, Post $post): void
    {
        if ($post->space_id !== $space->getKey()) {
            throw new NotFoundHttpException;
        }
    }

    private function audit(
        Space $space,
        Post $post,
        User $actor,
        SpaceAuditAction $action,
    ): void {
        SpaceAuditLog::query()->create([
            'space_id' => $space->getKey(),
            'actor_id' => $actor->getKey(),
            'action' => $action,
            'context' => ['post_id' => $post->getKey()],
        ]);
    }
}
