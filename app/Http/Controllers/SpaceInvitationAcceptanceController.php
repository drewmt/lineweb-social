<?php

namespace App\Http\Controllers;

use App\Community\SpaceMembershipManager;
use App\Models\SpaceInvitation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;

class SpaceInvitationAcceptanceController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        /** @var User $user */
        $user = $request->user();
        $invitation = SpaceInvitation::query()
            ->with(['space:id,name,slug,description', 'inviter:id,name'])
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $invitation instanceof SpaceInvitation) {
            throw (new ModelNotFoundException)->setModel(SpaceInvitation::class);
        }

        if (! hash_equals($invitation->email, Str::lower($user->email))) {
            throw new AuthorizationException('This invitation belongs to another account.');
        }

        if ($invitation->expires_at->isPast()) {
            throw new GoneHttpException('This invitation has expired.');
        }

        return Inertia::render('space-invitations/show', [
            'invitation' => [
                'space' => [
                    'name' => $invitation->space->name,
                    'description' => $invitation->space->description,
                ],
                'inviter' => $invitation->inviter?->name,
                'role' => $invitation->role->value,
                'expiresAt' => $invitation->expires_at->toIso8601String(),
                'available' => $invitation->isPending(),
            ],
            'acceptUrl' => route('space-invitations.accept', ['token' => $token]),
        ]);
    }

    public function store(
        Request $request,
        string $token,
        SpaceMembershipManager $memberships,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $space = $memberships->accept($token, $user);

        return to_route('spaces.show', $space)
            ->with('status', "Welcome to {$space->name}.");
    }
}
