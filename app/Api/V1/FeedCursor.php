<?php

namespace App\Api\V1;

use App\Exceptions\InvalidApiCursorException;
use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use JsonException;

final class FeedCursor
{
    /**
     * @return array{published_at: CarbonImmutable, post_id: int}
     */
    public function decode(
        string $cursor,
        User $viewer,
        ?Space $space,
        string $source = 'community',
    ): array {
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
            || array_keys($decoded) !== ['version', 'viewer_id', 'space_slug', 'source', 'published_at', 'post_id']
            || $decoded['version'] !== 2
            || $decoded['viewer_id'] !== $viewer->getKey()
            || $decoded['space_slug'] !== $space?->slug
            || $decoded['source'] !== $source
            || ! is_int($decoded['published_at'])
            || $decoded['published_at'] < 1
            || ! is_int($decoded['post_id'])
            || $decoded['post_id'] < 1) {
            throw new InvalidApiCursorException;
        }

        return [
            'published_at' => CarbonImmutable::createFromTimestampUTC($decoded['published_at']),
            'post_id' => $decoded['post_id'],
        ];
    }

    public function encode(
        User $viewer,
        ?Space $space,
        Post $post,
        string $source = 'community',
    ): string {
        $publishedAt = $post->published_at;

        if ($publishedAt === null) {
            throw new InvalidApiCursorException;
        }

        try {
            $payload = json_encode([
                'version' => 2,
                'viewer_id' => $viewer->getKey(),
                'space_slug' => $space?->slug,
                'source' => $source,
                'published_at' => $publishedAt->getTimestamp(),
                'post_id' => $post->getKey(),
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidApiCursorException;
        }

        return Crypt::encryptString($payload);
    }
}
