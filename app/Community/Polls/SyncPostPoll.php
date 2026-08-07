<?php

namespace App\Community\Polls;

use App\Models\Post;

final class SyncPostPoll
{
    public function replace(Post $post, ?PostPollDefinition $definition): void
    {
        $post->loadMissing('poll');

        if (! $definition instanceof PostPollDefinition) {
            $post->poll?->delete();
            $post->unsetRelation('poll');

            return;
        }

        $poll = $post->poll()->updateOrCreate([], [
            'question' => $definition->question,
            'closes_after_days' => $definition->closesAfterDays,
            'closes_at' => $post->published_at !== null && $definition->closesAfterDays !== null
                ? now()->addDays($definition->closesAfterDays)
                : null,
        ]);

        $poll->options()->delete();

        foreach ($definition->options as $position => $label) {
            $poll->options()->create([
                'position' => $position,
                'label' => $label,
            ]);
        }

        $post->unsetRelation('poll');
    }
}
