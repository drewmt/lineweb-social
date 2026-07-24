<?php

namespace App\Http\Controllers;

use App\Account\AccountDeletion;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SuspendedAccountController extends Controller
{
    public function __invoke(Request $request, AccountDeletion $accountDeletion): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->isSuspended(), 404);

        return Inertia::render('auth/suspended', [
            'account' => [
                'handle' => $user->handle,
                'emailVerified' => $user->hasVerifiedEmail(),
            ],
            'deletionBlockers' => $accountDeletion
                ->blockersFor($user)
                ->map(fn ($space): array => [
                    'name' => $space->name,
                    'manage_url' => null,
                ])
                ->values(),
        ]);
    }
}
