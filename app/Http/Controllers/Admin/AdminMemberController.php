<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminMemberController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', 'active', 'suspended', 'administrators', 'unverified'])],
        ]);
        $query = trim((string) ($validated['q'] ?? ''));
        $filter = (string) ($validated['status'] ?? 'all');

        $members = User::query()
            ->when($query !== '', fn (Builder $members) => $members
                ->where(function (Builder $search) use ($query): void {
                    $search
                        ->where('name', 'like', "%{$query}%")
                        ->orWhere('handle', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%");
                }))
            ->when($filter === 'active', fn (Builder $members) => $members
                ->whereNull('suspended_at'))
            ->when($filter === 'suspended', fn (Builder $members) => $members
                ->whereNotNull('suspended_at'))
            ->when($filter === 'administrators', fn (Builder $members) => $members
                ->where('platform_role', PlatformRole::Administrator))
            ->when($filter === 'unverified', fn (Builder $members) => $members
                ->whereNull('email_verified_at'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $member): array => [
                'id' => $member->getKey(),
                'name' => $member->name,
                'handle' => $member->handle,
                'email' => $member->email,
                'platformRole' => $member->platform_role->value,
                'emailVerifiedAt' => $member->email_verified_at?->toIso8601String(),
                'suspendedAt' => $member->suspended_at?->toIso8601String(),
                'suspensionReason' => $member->suspension_reason,
                'joinedAt' => $member->created_at?->toIso8601String(),
                'isSelf' => $member->is($request->user()),
                'canSuspend' => ! $member->isAdministrator() && ! $member->is($request->user()),
            ]);

        return Inertia::render('admin/members', [
            'query' => $query,
            'filter' => $filter,
            'counts' => [
                'all' => User::query()->count(),
                'active' => User::query()->whereNull('suspended_at')->count(),
                'suspended' => User::query()->whereNotNull('suspended_at')->count(),
                'administrators' => User::query()
                    ->where('platform_role', PlatformRole::Administrator)
                    ->count(),
                'unverified' => User::query()->whereNull('email_verified_at')->count(),
            ],
            'members' => $members,
        ]);
    }
}
