<?php

namespace App\Community\Topics;

use App\Models\Post;
use App\Models\Topic;

final class SyncPostTopics
{
    public function __construct(private readonly TopicParser $parser) {}

    public function sync(Post $post): void
    {
        $names = $this->parser->names($post->body);

        if ($names === []) {
            $post->topics()->sync([]);

            return;
        }

        Topic::query()->insertOrIgnore(
            array_map(
                fn (string $name): array => ['name' => $name],
                $names,
            ),
        );

        $topicIds = Topic::query()
            ->whereIn('name', $names)
            ->pluck('id');

        $post->topics()->sync($topicIds);
        $post->unsetRelation('topics');
    }
}
