<?php

namespace App\Community\Polls;

use App\Models\Post;
use App\Models\PostPoll;
use App\Models\PostPollOption;
use App\Models\PostPollVote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class VoteOnPostPoll
{
    /** @return array{changed: bool, previousOptionId: int|null} */
    public function cast(User $voter, Post $post, int $optionId): array
    {
        return DB::transaction(function () use ($voter, $post, $optionId): array {
            $lockedPost = Post::query()
                ->with(['author', 'space', 'poll.options'])
                ->whereKey($post->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            Gate::forUser($voter)->authorize('votePoll', $lockedPost);

            $poll = $lockedPost->poll;

            if (! $poll instanceof PostPoll || $poll->isClosed()) {
                throw ValidationException::withMessages([
                    'option_id' => 'This poll is closed.',
                ]);
            }

            $option = $poll->options->firstWhere('id', $optionId);

            if (! $option instanceof PostPollOption) {
                throw ValidationException::withMessages([
                    'option_id' => 'Choose an option from this poll.',
                ]);
            }

            $vote = PostPollVote::query()
                ->where('post_poll_id', $poll->getKey())
                ->where('user_id', $voter->getKey())
                ->lockForUpdate()
                ->first();
            $previousOptionId = $vote?->post_poll_option_id;

            if ($previousOptionId === $option->getKey()) {
                return [
                    'changed' => false,
                    'previousOptionId' => $previousOptionId,
                ];
            }

            PostPollVote::query()->updateOrCreate(
                [
                    'post_poll_id' => $poll->getKey(),
                    'user_id' => $voter->getKey(),
                ],
                ['post_poll_option_id' => $option->getKey()],
            );

            return [
                'changed' => true,
                'previousOptionId' => $previousOptionId,
            ];
        });
    }
}
