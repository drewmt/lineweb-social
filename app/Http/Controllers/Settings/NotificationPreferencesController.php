<?php

namespace App\Http\Controllers\Settings;

use App\Enums\NotificationDigestFrequency;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateNotificationPreferencesRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                'contentMentions' => $preferences->exists ? $preferences->content_mentions : true,
                'spaceModeration' => $preferences->exists ? $preferences->space_moderation : true,
                'emailDigestFrequency' => $preferences->exists
                    ? $preferences->email_digest_frequency->value
                    : NotificationDigestFrequency::Off->value,
            ],
        ]);
    }

    public function update(UpdateNotificationPreferencesRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $preferences = $request->user()
                ->notificationPreference()
                ->lockForUpdate()
                ->firstOrNew();
            $wasDaily = $preferences->exists
                && $preferences->email_digest_frequency === NotificationDigestFrequency::Daily;

            $preferences->fill($request->validated());

            if ($preferences->email_digest_frequency === NotificationDigestFrequency::Daily) {
                if (! $wasDaily) {
                    $preferences->email_digest_cursor_at = now()->startOfSecond();
                    $preferences->email_digest_cursor_notification_id = null;
                }
            } else {
                $preferences->email_digest_cursor_at = null;
                $preferences->email_digest_cursor_notification_id = null;
            }

            $preferences->save();
        });

        return back()->with('status', 'Notification preferences saved.');
    }
}
