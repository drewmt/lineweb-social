<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePlatformSuspensionRequest;
use App\Models\User;
use App\Platform\PlatformAdministration;
use Illuminate\Http\RedirectResponse;

class MemberSuspensionController extends Controller
{
    public function store(
        UpdatePlatformSuspensionRequest $request,
        User $member,
        PlatformAdministration $administration,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $administration->suspend(
            $member,
            $actor,
            $request->string('reason')->toString(),
        );

        return to_route('admin.members.index', $this->directoryQuery($request))
            ->with('status', "{$member->name}'s account was suspended.");
    }

    public function destroy(
        UpdatePlatformSuspensionRequest $request,
        User $member,
        PlatformAdministration $administration,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $administration->reinstate(
            $member,
            $actor,
            $request->string('reason')->toString(),
        );

        return to_route('admin.members.index', $this->directoryQuery($request))
            ->with('status', "{$member->name}'s access was restored.");
    }

    /** @return array{q?: string, status?: string} */
    private function directoryQuery(UpdatePlatformSuspensionRequest $request): array
    {
        $query = trim((string) $request->validated('q', ''));
        $status = (string) $request->validated('status', 'all');

        return array_filter([
            'q' => $query,
            'status' => $status === 'all' ? null : $status,
        ], fn (?string $value): bool => $value !== null && $value !== '');
    }
}
