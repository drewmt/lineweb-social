<?php

namespace App\Http\Controllers;

use App\Community\NotificationCenter;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request, NotificationCenter $center): Response
    {
        /** @var User $user */
        $user = $request->user();
        $filter = $request->query('filter') === 'unread' ? 'unread' : 'all';

        return Inertia::render('notifications/index', [
            ...$center->for($user, $filter),
            'filter' => $filter,
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

    public function readAll(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        DatabaseNotification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('status', 'All notifications marked as read.');
    }
}
