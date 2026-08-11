<?php

namespace App\Http\Controllers\Api\V1;

use App\Api\V1\SpaceEventCursor;
use App\Community\SpaceEventProjection;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SpaceEventResource;
use App\Models\Space;
use App\Models\SpaceEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SpaceEventIndexController extends Controller
{
    public function __invoke(
        Request $request,
        Space $space,
        SpaceEventProjection $events,
        SpaceEventCursor $cursors,
    ): JsonResponse {
        Gate::authorize('view', $space);

        /** @var User $viewer */
        $viewer = $request->user();
        /** @var array{limit?: int, cursor?: string, scope?: string} $validated */
        $validated = $request->validate([
            'cursor' => ['sometimes', 'string', 'max:2048'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'scope' => ['sometimes', Rule::in(['upcoming', 'past'])],
        ]);
        $limit = (int) ($validated['limit'] ?? 20);
        $scope = $validated['scope'] ?? 'upcoming';
        $query = $events->query($viewer, $space);

        if (isset($validated['cursor'])) {
            $cursor = $cursors->decode($validated['cursor'], $viewer, $space, $scope);

            $query->where(function (Builder $events) use ($cursor, $scope): void {
                $comparison = $scope === 'past' ? '<' : '>';

                $events
                    ->where('starts_at', $comparison, $cursor['starts_at'])
                    ->orWhere(function (Builder $events) use ($comparison, $cursor): void {
                        $events
                            ->where('starts_at', $cursor['starts_at'])
                            ->where('id', $comparison, $cursor['event_id']);
                    });
            });
        }

        if ($scope === 'past') {
            $query
                ->where('ends_at', '<', now())
                ->orderByDesc('starts_at')
                ->orderByDesc('id');
        } else {
            $query
                ->where('ends_at', '>=', now())
                ->orderBy('starts_at')
                ->orderBy('id');
        }

        /** @var Collection<int, SpaceEvent> $spaceEvents */
        $spaceEvents = $query->limit($limit + 1)->get();
        $hasMore = $spaceEvents->count() > $limit;
        $spaceEvents = $spaceEvents->take($limit)->values();
        $lastEvent = $spaceEvents->last();
        $nextCursor = $hasMore && $lastEvent instanceof SpaceEvent
            ? $cursors->encode($viewer, $space, $scope, $lastEvent)
            : null;
        $next = $nextCursor !== null
            ? route('api.v1.spaces.events.index', [
                'space' => $space,
                'cursor' => $nextCursor,
                'limit' => $limit,
                'scope' => $scope,
            ])
            : null;

        return response()->json([
            'data' => $spaceEvents
                ->map(fn (SpaceEvent $event): array => (new SpaceEventResource($event))->toArray($request))
                ->values()
                ->all(),
            'links' => ['next' => $next],
            'meta' => [
                'next_cursor' => $nextCursor,
                'has_more' => $hasMore,
                'limit' => $limit,
                'scope' => $scope,
            ],
        ]);
    }
}
