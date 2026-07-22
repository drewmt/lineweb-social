<?php

namespace App\Api\V1;

use App\Exceptions\InvalidApiCursorException;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use JsonException;

final class PostCommentCursor
{
    /**
     * @return array{published_at: CarbonImmutable, comment_id: int}
     */
    public function decode(string $cursor, User $viewer, Post $post): array
    {
        try {
            /** @var mixed $decoded */
            $decoded = json_decode(
                Crypt::decryptString($cursor),
                true,
                8,
                JSON_THROW_ON_ERROR,
            );
        } catch (DecryptException|JsonException) {
            throw new InvalidApiCursorException;
        }

        if (! is_array($decoded)
            || array_keys($decoded) !== ['version', 'viewer_id', 'post_id', 'published_at', 'comment_id']
            || $decoded['version'] !== 1
            || $decoded['viewer_id'] !== $viewer->getKey()
            || $decoded['post_id'] !== $post->getKey()
            || ! is_int($decoded['published_at'])
            || $decoded['published_at'] < 1
            || ! is_int($decoded['comment_id'])
            || $decoded['comment_id'] < 1) {
            throw new InvalidApiCursorException;
        }

        return [
            'published_at' => CarbonImmutable::createFromTimestampUTC($decoded['published_at']),
            'comment_id' => $decoded['comment_id'],
        ];
    }

    public function encode(User $viewer, Post $post, Comment $comment): string
    {
        if ($comment->post_id !== $post->getKey()) {
            throw new InvalidApiCursorException;
        }

        try {
            $payload = json_encode([
                'version' => 1,
                'viewer_id' => $viewer->getKey(),
                'post_id' => $post->getKey(),
                'published_at' => $comment->published_at->getTimestamp(),
                'comment_id' => $comment->getKey(),
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidApiCursorException;
        }

        return Crypt::encryptString($payload);
    }
}
