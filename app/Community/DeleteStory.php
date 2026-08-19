<?php

namespace App\Community;

use App\Enums\SpaceAuditAction;
use App\Models\SpaceAuditLog;
use App\Models\Story;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class DeleteStory
{
    public function handle(User $actor, Story $story): void
    {
        DB::transaction(function () use ($actor, $story): void {
            $lockedStory = Story::query()
                ->with('space')
                ->whereKey($story->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            Gate::forUser($actor)->authorize('delete', $lockedStory);

            if ($lockedStory->user_id !== $actor->getKey()) {
                SpaceAuditLog::query()->create([
                    'space_id' => $lockedStory->space_id,
                    'actor_id' => $actor->getKey(),
                    'subject_user_id' => $lockedStory->user_id,
                    'action' => SpaceAuditAction::StoryRemoved,
                    'context' => ['story_id' => $lockedStory->getKey()],
                ]);
            }

            $lockedStory->delete();
        });
    }
}
