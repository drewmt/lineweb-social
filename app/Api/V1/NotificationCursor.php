<?php

namespace App\Api\V1;

use App\Enums\NotificationType;
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
    public function decode(string $cursor, User $viewer, string $filter, ?NotificationType $kind = null): array
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

        $legacyKeys = ['version', 'viewer_id', 'filter', 'created_at', 'notification_id'];
        $scopedKeys = ['version', 'viewer_id', 'filter', 'kind', 'created_at', 'notification_id'];

        if (! is_array($decoded)
            || ! in_array(array_keys($decoded), [$legacyKeys, $scopedKeys], true)
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

        if (array_keys($decoded) === $scopedKeys) {
            $kindValues = array_map(
                static fn (NotificationType $case): string => $case->value,
                NotificationType::cases(),
            );

            if (! array_key_exists('kind', $decoded)
                || ! is_string($decoded['kind'])
                || $kind === null
                || $decoded['kind'] !== $kind->value
                || ! in_array($decoded['kind'], $kindValues, true)) {
                throw new InvalidApiCursorException;
            }

            return [
                'created_at' => CarbonImmutable::createFromTimestampUTC($decoded['created_at']),
                'notification_id' => $decoded['notification_id'],
            ];
        }

        if ($kind !== null) {
            throw new InvalidApiCursorException;
        }

        return [
            'created_at' => CarbonImmutable::createFromTimestampUTC($decoded['created_at']),
            'notification_id' => $decoded['notification_id'],
        ];
    }

    public function encode(User $viewer, string $filter, ?NotificationType $kind, DatabaseNotification $notification): string
    {
        if ($notification->created_at === null || $notification->getKey() === null) {
            throw new InvalidApiCursorException;
        }

        try {
            $payload = json_encode([
                'version' => 1,
                'viewer_id' => $viewer->getKey(),
                'filter' => $filter,
                ...($kind === null ? [] : ['kind' => $kind->value]),
                'created_at' => $notification->created_at->getTimestamp(),
                'notification_id' => $notification->getKey(),
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidApiCursorException;
        }

        return Crypt::encryptString($payload);
    }
}
