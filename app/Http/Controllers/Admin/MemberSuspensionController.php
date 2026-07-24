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

        return to_route('admin.index', $this->dashboardQuery($request))
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

        return to_route('admin.index', $this->dashboardQuery($request))
            ->with('status', "{$member->name}'s access was restored.");
    }

    /** @return array{q: string}|array{} */
    private function dashboardQuery(UpdatePlatformSuspensionRequest $request): array
    {
        $query = trim((string) $request->validated('q', ''));

        return $query === '' ? [] : ['q' => $query];
    }
}
