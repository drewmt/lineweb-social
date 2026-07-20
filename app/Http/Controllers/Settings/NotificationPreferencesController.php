<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateNotificationPreferencesRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationPreferencesController extends Controller
{
    public function edit(Request $request): Response
    {
        $preferences = $request->user()->notificationPreference()->firstOrNew();

        return Inertia::render('settings/notifications', [
            'preferences' => [
                'commentReplies' => $preferences->exists ? $preferences->comment_replies : true,
                'spaceModeration' => $preferences->exists ? $preferences->space_moderation : true,
            ],
        ]);
    }

    public function update(UpdateNotificationPreferencesRequest $request): RedirectResponse
    {
        $request->user()->notificationPreference()->updateOrCreate(
            [],
            $request->validated(),
        );

        return back()->with('status', 'Notification preferences saved.');
    }
}
