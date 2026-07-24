<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isSuspended()) {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            throw new AuthorizationException;
        }

        return to_route('account.suspended');
    }
}
