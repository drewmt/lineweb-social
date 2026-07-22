<?php

namespace App\Http\Controllers\Api\V1;

use App\Api\V1\NotificationCursor;
use App\Community\NotificationCenter;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function __invoke(
        Request $request,
        NotificationCenter $center,
        NotificationCursor $cursorFactory,
    ): JsonResponse {
        /** @var User $viewer */
        $viewer = $request->user();

        /** @var array{cursor?: string, limit?: int, filter?: string} $validated */
        $validated = $request->validate([
            'cursor' => ['sometimes', 'string', 'max:2048'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'filter' => ['sometimes', 'string', 'in:all,unread'],
        ]);

        $filter = $validated['filter'] ?? 'all';
        $limit = (int) ($validated['limit'] ?? 20);

        $query = DatabaseNotification::query()
            ->where('notifiable_type', $viewer->getMorphClass())
            ->where('notifiable_id', $viewer->getKey())
            ->when($filter === 'unread', fn ($notifications) => $notifications->whereNull('read_at'))
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit + 1);

        if (isset($validated['cursor'])) {
            $cursor = $cursorFactory->decode($validated['cursor'], $viewer, $filter);

            $query->where(function ($notifications) use ($cursor): void {
                $notifications
                    ->where('created_at', '<', $cursor['created_at'])
                    ->orWhere(function ($notifications) use ($cursor): void {
                        $notifications
                            ->where('created_at', $cursor['created_at'])
                            ->where('id', '<', $cursor['notification_id']);
                    });
            });
        }

        /** @var Collection<int, DatabaseNotification> $notifications */
        $notifications = $query->get();
        $hasMore = $notifications->count() > $limit;
        $notifications = $notifications->take($limit)->values();

        $lastNotification = $notifications->last();
        $nextCursor = $hasMore && $lastNotification instanceof DatabaseNotification
            ? $cursorFactory->encode($viewer, $filter, $lastNotification)
            : null;

        $next = $nextCursor !== null
            ? route('api.v1.notifications', array_filter([
                'cursor' => $nextCursor,
                'limit' => $limit,
                'filter' => $filter === 'unread' ? $filter : null,
            ], static fn (mixed $value): bool => $value !== null))
            : null;

        return response()->json([
            'data' => $notifications
                ->map(fn (DatabaseNotification $notification): array => $center->apiItem($viewer, $notification))
                ->all(),
            'links' => [
                'next' => $next,
            ],
            'meta' => [
                'next_cursor' => $nextCursor,
                'has_more' => $hasMore,
                'limit' => $limit,
            ],
        ]);
    }

    public function readOne(
        Request $request,
        string $notification,
        NotificationCenter $center,
    ): JsonResponse {
        /** @var User $viewer */
        $viewer = $request->user();

        $center->findFor($viewer, $notification)->markAsRead();

        return response()->json(null, 204);
    }

    public function readAll(Request $request): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();

        DatabaseNotification::query()
            ->where('notifiable_type', $viewer->getMorphClass())
            ->where('notifiable_id', $viewer->getKey())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(null, 204);
    }
}
