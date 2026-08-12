<?php

namespace App\Http\Controllers;

use App\Community\NotificationCenter;
use App\Enums\NotificationType;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request, NotificationCenter $center): Response
    {
        /** @var User $user */
        $user = $request->user();
        $filter = $request->query('filter') === 'unread' ? 'unread' : 'all';
        $kind = NotificationType::tryFrom((string) Arr::get($request->query(), 'kind'));

        return Inertia::render('notifications/index', [
            ...$center->for($user, $filter, $kind),
            'filter' => $filter,
            'kind' => $kind instanceof NotificationType ? $kind->value : 'all',
        ]);
    }

    public function open(Request $request, string $notification, NotificationCenter $center): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $record = $center->findFor($user, $notification);
        $destination = $center->destination($user, $record);
        $record->markAsRead();

        if ($destination === null) {
            return redirect()->route('notifications.index')
                ->with('status', 'This notification is no longer available.');
        }

        return redirect()->to($destination);
    }

    public function read(Request $request, string $notification, NotificationCenter $center): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $center->findFor($user, $notification)->markAsRead();

        return back()->with('status', 'Notification marked as read.');
    }

    public function readAll(Request $request, NotificationCenter $center): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var array{kind?: string} $validated */
        $validated = $request->validate([
            'kind' => ['sometimes', 'string', 'in:all,comment_reply,content_mention,space_moderation'],
        ]);

        $kind = isset($validated['kind']) && $validated['kind'] !== 'all'
            ? NotificationType::tryFrom($validated['kind'])
            : null;

        $center->markAllAsRead($user, $kind);

        return back()->with('status', 'All notifications marked as read.');
    }
}
