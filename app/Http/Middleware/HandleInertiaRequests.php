<?php

namespace App\Http\Middleware;

use App\Community\PrivateMessaging;
use App\Models\User;
use App\Platform\Extensions\ExtensionAssetManager;
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
        $coreVersion = parent::version($request) ?? '';
        $extensionVersion = app(ExtensionAssetManager::class)->payload()['version'];

        return hash('sha256', "{$coreVersion}|{$extensionVersion}");
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
            'extensionAssets' => app(ExtensionAssetManager::class)->payload(),
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
            'draftSummary' => fn (): array => [
                'count' => $request->user()?->posts()
                    ->whereNull('published_at')
                    ->whereNull('hidden_at')
                    ->count() ?? 0,
            ],
            'status' => fn () => $request->session()->get('status'),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
