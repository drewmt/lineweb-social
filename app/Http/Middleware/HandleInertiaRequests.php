<?php

namespace App\Http\Middleware;

use App\Community\PrivateMessaging;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'notificationSummary' => fn (): array => [
                'unreadCount' => $request->user()?->unreadNotifications()->count() ?? 0,
            ],
            'messageSummary' => function () use ($request): array {
                $user = $request->user();

                return [
                    'unreadCount' => $user instanceof User
                        ? app(PrivateMessaging::class)->unreadCount($user)
                        : 0,
                ];
            },
            'status' => fn () => $request->session()->get('status'),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
