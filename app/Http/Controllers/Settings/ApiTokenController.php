<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreApiTokenRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ApiTokenController extends Controller
{
    private const ABILITIES = ['profile:read'];

    private const MAX_ACTIVE_TOKENS = 10;

    public function store(StoreApiTokenRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $activeTokens = $user->tokens()
            ->where(function ($tokens): void {
                $tokens
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->count();

        if ($activeTokens >= self::MAX_ACTIVE_TOKENS) {
            throw ValidationException::withMessages([
                'name' => 'Revoke an existing token before creating another one.',
            ]);
        }

        $expiresAt = now()->addDays(30);
        $newToken = $user->createToken(
            $request->string('name')->toString(),
            self::ABILITIES,
            $expiresAt,
        );

        Inertia::flash([
            'apiToken' => [
                'name' => $newToken->accessToken->name,
                'plainTextToken' => $newToken->plainTextToken,
                'expiresAt' => $expiresAt->toIso8601String(),
            ],
            'toast' => [
                'type' => 'success',
                'message' => __('API token created. Copy it now.'),
            ],
        ]);

        return back();
    }

    public function destroy(Request $request, string $apiToken): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->tokens()->findOrFail($apiToken)->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('API token revoked.'),
        ]);

        return back();
    }

    public function destroyAll(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->tokens()->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('All API tokens revoked.'),
        ]);

        return back();
    }
}
