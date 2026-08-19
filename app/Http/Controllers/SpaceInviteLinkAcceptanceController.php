<?php

namespace App\Http\Controllers;

use App\Community\SpaceInviteLinks;
use App\Models\SpaceInviteLink;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SpaceInviteLinkAcceptanceController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $inviteLink = SpaceInviteLink::query()
            ->with(['space:id,name,slug,description', 'creator:id,name'])
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $inviteLink instanceof SpaceInviteLink) {
            throw (new ModelNotFoundException)->setModel(SpaceInviteLink::class);
        }

        $user = $request->user();
        $alreadyMember = $user instanceof User && $inviteLink->space->hasMember($user);

        if ($inviteLink->isAvailable() && (! $user instanceof User || ! $user->hasVerifiedEmail())) {
            $request->session()->put('pending_space_invite', $token);
        }

        if (! $user instanceof User && $inviteLink->isAvailable()) {
            $request->session()->put('url.intended', route('space-invite-links.show', ['token' => $token]));
        }

        if (! $inviteLink->isAvailable() && $request->session()->get('pending_space_invite') === $token) {
            $request->session()->forget('pending_space_invite');
        }

        return Inertia::render('space-invite-links/show', [
            'inviteLink' => [
                'space' => [
                    'name' => $inviteLink->space->name,
                    'slug' => $inviteLink->space->slug,
                    'description' => $inviteLink->space->description,
                ],
                'creator' => $inviteLink->creator?->name,
                'expiresAt' => $inviteLink->expires_at->toIso8601String(),
                'remainingUses' => $inviteLink->remainingUses(),
                'available' => $inviteLink->isAvailable(),
                'alreadyMember' => $alreadyMember,
            ],
            'viewer' => [
                'signedIn' => $user instanceof User,
                'verified' => $user?->hasVerifiedEmail() ?? false,
                'suspended' => $user?->isSuspended() ?? false,
            ],
            'loginUrl' => route('login'),
            'registerUrl' => route('register'),
            'acceptUrl' => route('space-invite-links.accept', ['token' => $token]),
            'spaceUrl' => $alreadyMember ? route('spaces.show', $inviteLink->space) : null,
        ]);
    }

    public function store(
        Request $request,
        string $token,
        SpaceInviteLinks $inviteLinks,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $space = $inviteLinks->accept($token, $user);
        $request->session()->forget('pending_space_invite');

        return to_route('spaces.show', $space)
            ->with('status', "You're now a member of {$space->name}.");
    }
}
