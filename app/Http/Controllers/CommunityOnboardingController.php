<?php

namespace App\Http\Controllers;

use App\Community\CommunityOnboarding;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CommunityOnboardingController extends Controller
{
    public function show(Request $request, CommunityOnboarding $onboarding): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('onboarding/show', $onboarding->for($user));
    }

    public function dismiss(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->forceFill(['onboarding_dismissed_at' => now()])->save();

        return to_route('feed')->with(
            'status',
            'Getting started is available whenever you need it.',
        );
    }
}
