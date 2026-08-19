<?php

namespace App\Http\Controllers;

use App\Community\SpaceInviteLinks;
use App\Http\Requests\StoreSpaceInviteLinkRequest;
use App\Models\Space;
use App\Models\SpaceInviteLink;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SpaceInviteLinkController extends Controller
{
    public function store(
        StoreSpaceInviteLinkRequest $request,
        Space $space,
        SpaceInviteLinks $inviteLinks,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $result = $inviteLinks->create(
            $space,
            $actor,
            $request->string('label')->toString(),
            $request->integer('expires_in_days'),
            $request->integer('max_uses'),
        );

        return to_route('spaces.manage', $space)
            ->with('status', 'Invite link created. Copy it before leaving this page.')
            ->with('spaceInviteLink', [
                'url' => route('space-invite-links.show', ['token' => $result->token]),
                'expiresAt' => $result->inviteLink->expires_at->toIso8601String(),
                'maxUses' => $result->inviteLink->max_uses,
            ]);
    }

    public function destroy(
        Request $request,
        Space $space,
        SpaceInviteLink $inviteLink,
        SpaceInviteLinks $inviteLinks,
    ): RedirectResponse {
        Gate::authorize('revokeInviteLink', [$space, $inviteLink]);

        /** @var User $actor */
        $actor = $request->user();
        $inviteLinks->revoke($space, $inviteLink, $actor);

        return to_route('spaces.manage', $space)
            ->with('status', 'Invite link revoked.');
    }
}
