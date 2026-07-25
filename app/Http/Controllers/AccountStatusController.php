<?php

namespace App\Http\Controllers;

use App\Account\AccountDeletion;
use App\Models\PlatformAppeal;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountStatusController extends Controller
{
    public function __invoke(
        Request $request,
        AccountDeletion $accountDeletion,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $appeal = PlatformAppeal::query()
            ->whereBelongsTo($user)
            ->latest('id')
            ->first();
        $currentAppeal = $user->suspended_at === null
            ? null
            : PlatformAppeal::query()
                ->where('suspension_reference', $user->suspension_reference)
                ->first();

        return Inertia::render('account/status', [
            'account' => [
                'handle' => $user->handle,
                'emailVerified' => $user->hasVerifiedEmail(),
                'restricted' => $user->isSuspended(),
                'restrictedAt' => $user->suspended_at?->toIso8601String(),
            ],
            'appeal' => $appeal instanceof PlatformAppeal
                ? [
                    'status' => $appeal->status->value,
                    'statusLabel' => $appeal->status->label(),
                    'statement' => $appeal->statement,
                    'decisionMessage' => $appeal->decision_message,
                    'submittedAt' => $appeal->created_at->toIso8601String(),
                    'reviewedAt' => $appeal->reviewed_at?->toIso8601String(),
                ]
                : null,
            'canAppeal' => $user->isSuspended()
                && ! $currentAppeal instanceof PlatformAppeal,
            'deletionBlockers' => $user->isSuspended()
                ? $accountDeletion
                    ->blockersFor($user)
                    ->map(fn ($space): array => [
                        'name' => $space->name,
                        'manage_url' => null,
                    ])
                    ->values()
                : [],
        ]);
    }
}
