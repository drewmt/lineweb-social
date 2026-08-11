<?php

namespace App\Api\V1;

use App\Exceptions\InvalidApiCursorException;
use App\Models\Space;
use App\Models\SpaceEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use JsonException;

final class SpaceEventCursor
{
    /** @return array{starts_at: CarbonImmutable, event_id: int} */
    public function decode(string $cursor, User $viewer, Space $space, string $scope): array
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
            || array_keys($decoded) !== ['version', 'viewer_id', 'space_id', 'scope', 'starts_at', 'event_id']
            || $decoded['version'] !== 1
            || $decoded['viewer_id'] !== $viewer->getKey()
            || $decoded['space_id'] !== $space->getKey()
            || $decoded['scope'] !== $scope
            || ! is_int($decoded['starts_at'])
            || $decoded['starts_at'] < 1
            || ! is_int($decoded['event_id'])
            || $decoded['event_id'] < 1) {
            throw new InvalidApiCursorException;
        }

        return [
            'starts_at' => CarbonImmutable::createFromTimestampUTC($decoded['starts_at']),
            'event_id' => $decoded['event_id'],
        ];
    }

    public function encode(User $viewer, Space $space, string $scope, SpaceEvent $event): string
    {
        if ($event->space_id !== $space->getKey()) {
            throw new InvalidApiCursorException;
        }

        try {
            $payload = json_encode([
                'version' => 1,
                'viewer_id' => $viewer->getKey(),
                'space_id' => $space->getKey(),
                'scope' => $scope,
                'starts_at' => $event->starts_at->getTimestamp(),
                'event_id' => $event->getKey(),
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidApiCursorException;
        }

        return Crypt::encryptString($payload);
    }
}
