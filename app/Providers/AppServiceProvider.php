<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );

        RateLimiter::for('post-publishing', fn (Request $request): Limit => Limit::perMinute(10)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('post-reporting', fn (Request $request): Limit => Limit::perHour(10)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('comment-publishing', fn (Request $request): Limit => Limit::perMinute(20)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('comment-reporting', fn (Request $request): Limit => Limit::perHour(10)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('space-creation', fn (Request $request): Limit => Limit::perHour(5)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('space-membership', fn (Request $request): Limit => Limit::perMinute(15)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('space-invitations', fn (Request $request): Limit => Limit::perHour(20)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('space-moderation', fn (Request $request): Limit => Limit::perMinute(30)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('user-safety', fn (Request $request): Limit => Limit::perMinute(30)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('notification-actions', fn (Request $request): Limit => Limit::perMinute(60)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('api-read', function (Request $request): Limit {
            $token = $request->user()?->currentAccessToken();
            $tokenId = $token instanceof PersonalAccessToken ? $token->getKey() : 'none';
            $userId = $request->user()?->getAuthIdentifier() ?? 'guest';

            return Limit::perMinute(120)
                ->by("api-read:{$userId}:{$tokenId}");
        });

        RateLimiter::for('api-token-management', fn (Request $request): Limit => Limit::perMinute(10)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
