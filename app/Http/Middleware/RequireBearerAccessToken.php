<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class RequireBearerAccessToken
{
    /**
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() === null
            || ! ($request->user()?->currentAccessToken() instanceof PersonalAccessToken)) {
            throw new AuthenticationException;
        }

        return $next($request);
    }
}
