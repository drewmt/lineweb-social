<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SpaceResource;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SpaceIndexController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var array{limit?: int, cursor?: string} $validated */
        $validated = $request->validate([
            'cursor' => ['sometimes', 'string', 'max:2048'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $limit = (int) ($validated['limit'] ?? 20);

        $paginator = Space::query()
            ->discoverableBy($user)
            ->addSelect([
                'current_role' => DB::table('space_members')
                    ->select('role')
                    ->whereColumn('space_members.space_id', 'spaces.id')
                    ->where('space_members.user_id', $user->getKey())
                    ->limit(1),
            ])
            ->withExists([
                'members as is_member' => fn (Builder $query) => $query->whereKey($user->getKey()),
            ])
            ->withCount('members')
            ->orderBy('name')
            ->orderBy('id')
            ->cursorPaginate($limit)
            ->withQueryString();

        return response()->json([
            'data' => $paginator->getCollection()
                ->map(fn (Space $space): array => (new SpaceResource($space))->toArray($request))
                ->values()
                ->all(),
            'links' => [
                'next' => $paginator->nextPageUrl(),
            ],
            'meta' => [
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'has_more' => $paginator->hasMorePages(),
                'limit' => $limit,
            ],
        ]);
    }
}
