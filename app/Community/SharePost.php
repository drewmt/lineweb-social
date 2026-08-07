<?php

namespace App\Community;

use App\Community\Mentions\MentionNotifier;
use App\Community\Mentions\MentionParser;
use App\Community\Topics\SyncPostTopics;
use App\Events\PostPublished;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class SharePost
{
    public function __construct(
        private readonly MentionParser $mentionParser,
        private readonly MentionNotifier $mentionNotifier,
        private readonly SyncPostTopics $topics,
    ) {}

    public function share(User $author, Post $post, string $body): Post
    {
        $result = DB::transaction(function () use ($author, $post, $body): array {
            $source = Post::query()
                ->with('space')
                ->whereKey($post->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($author)->authorize('share', $source);

            $share = Post::query()
                ->where('user_id', $author->getKey())
                ->where('shared_post_id', $source->getKey())
                ->lockForUpdate()
                ->first();

            if (! $share instanceof Post) {
                $share = $source->space->posts()->create([
                    'user_id' => $author->getKey(),
                    'body' => $body,
                    'shared_post_id' => $source->getKey(),
                    'published_at' => now(),
                ]);
                $this->topics->sync($share);

                return [
                    'post' => $share,
                    'created' => true,
                    'changed' => true,
                    'previousHandles' => [],
                ];
            }

            if ($share->body === $body) {
                return [
                    'post' => $share,
                    'created' => false,
                    'changed' => false,
                    'previousHandles' => [],
                ];
            }

            $previousHandles = $this->mentionParser->handles($share->body);
            $share->update([
                'body' => $body,
                'edited_at' => now(),
            ]);
            $this->topics->sync($share);

            return [
                'post' => $share,
                'created' => false,
                'changed' => true,
                'previousHandles' => $previousHandles,
            ];
        });

        /** @var Post $sharedPost */
        $sharedPost = $result['post'];

        if ($result['created']) {
            PostPublished::dispatch($sharedPost);
        } elseif ($result['changed']) {
            $this->mentionNotifier->forPost($sharedPost->refresh(), $result['previousHandles']);
        }

        return $sharedPost;
    }
}
