<?php

namespace App\Community\Polls;

use App\Models\Post;
use App\Models\PostPoll;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class PostPollProjection
{
    /**
     * Posts must already be filtered or authorized for the viewer.
     *
     * @param  Collection<int, Post>  $posts
     * @return array<int, array{question: string, options: list<array{id: int, label: string, votes: int|null, percentage: int|null}>, totalVotes: int|null, viewerOptionId: int|null, canVote: bool, isClosed: bool, closesAt: string|null, showResults: bool}>
     */
    public function forPosts(Collection $posts, User $viewer): array
    {
        $postIds = $posts->modelKeys();

        if ($postIds === []) {
            return [];
        }

        /** @var Collection<int, PostPoll> $polls */
        $polls = PostPoll::query()
            ->with('options')
            ->whereIn('post_id', $postIds)
            ->get()
            ->keyBy('post_id');

        if ($polls->isEmpty()) {
            return [];
        }

        $pollIds = $polls->modelKeys();
        $counts = DB::table('post_poll_votes')
            ->select('post_poll_option_id', DB::raw('COUNT(*) as aggregate'))
            ->whereIn('post_poll_id', $pollIds)
            ->groupBy('post_poll_option_id')
            ->pluck('aggregate', 'post_poll_option_id');
        $viewerOptionIds = DB::table('post_poll_votes')
            ->where('user_id', $viewer->getKey())
            ->whereIn('post_poll_id', $pollIds)
            ->pluck('post_poll_option_id', 'post_poll_id');
        $memberSpaceIds = DB::table('space_members')
            ->where('user_id', $viewer->getKey())
            ->whereIn('space_id', $posts->pluck('space_id')->unique())
            ->pluck('space_id')
            ->all();
        $projection = [];

        foreach ($posts as $post) {
            $poll = $polls->get($post->getKey());

            if (! $poll instanceof PostPoll) {
                continue;
            }

            $viewerOptionId = $viewerOptionIds->get($poll->getKey());
            $viewerOptionId = is_numeric($viewerOptionId) ? (int) $viewerOptionId : null;
            $isClosed = $poll->isClosed();
            $showResults = $viewerOptionId !== null
                || $isClosed
                || $post->user_id === $viewer->getKey();
            $totalVotes = 0;

            foreach ($poll->options as $option) {
                $totalVotes += (int) ($counts->get($option->id) ?? 0);
            }
            $canVote = ! $isClosed
                && $post->published_at !== null
                && $post->hidden_at === null
                && in_array($post->space_id, $memberSpaceIds, true);

            $options = [];

            foreach ($poll->options as $option) {
                $votes = (int) ($counts->get($option->id) ?? 0);
                $options[] = [
                    'id' => $option->id,
                    'label' => $option->label,
                    'votes' => $showResults ? $votes : null,
                    'percentage' => $showResults && $totalVotes > 0
                        ? (int) round(($votes / $totalVotes) * 100)
                        : null,
                ];
            }

            $projection[$post->getKey()] = [
                'question' => $poll->question,
                'options' => $options,
                'totalVotes' => $showResults ? $totalVotes : null,
                'viewerOptionId' => $viewerOptionId,
                'canVote' => $canVote,
                'isClosed' => $isClosed,
                'closesAt' => $poll->closes_at?->toIso8601String(),
                'showResults' => $showResults,
            ];
        }

        return $projection;
    }
}
