<?php

namespace App\Api\V1;

use App\Exceptions\InvalidApiCursorException;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Crypt;
use JsonException;

final class NotificationCursor
{
    /**
     * @return array{created_at: CarbonImmutable, notification_id: string}
     */
    public function decode(string $cursor, User $viewer, string $filter): array
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
            || array_keys($decoded) !== ['version', 'viewer_id', 'filter', 'created_at', 'notification_id']
            || $decoded['version'] !== 1
            || $decoded['viewer_id'] !== $viewer->getKey()
            || ! is_string($decoded['filter'])
            || $decoded['filter'] !== $filter
            || ! is_int($decoded['created_at'])
            || $decoded['created_at'] < 1
            || ! is_string($decoded['notification_id'])
            || $decoded['notification_id'] === '') {
            throw new InvalidApiCursorException;
        }

        return [
            'created_at' => CarbonImmutable::createFromTimestampUTC($decoded['created_at']),
            'notification_id' => $decoded['notification_id'],
        ];
    }

    public function encode(User $viewer, string $filter, DatabaseNotification $notification): string
    {
        if ($notification->created_at === null || $notification->getKey() === null) {
            throw new InvalidApiCursorException;
        }

        try {
            $payload = json_encode([
                'version' => 1,
                'viewer_id' => $viewer->getKey(),
                'filter' => $filter,
                'created_at' => $notification->created_at->getTimestamp(),
                'notification_id' => $notification->getKey(),
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidApiCursorException;
        }

        return Crypt::encryptString($payload);
    }
}
