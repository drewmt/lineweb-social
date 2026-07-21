<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignApiRequestId
{
    public const ATTRIBUTE = 'lineweb_api_request_id';

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = self::for($request);
        $response = $next($request);
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }

    public static function for(Request $request): string
    {
        $requestId = $request->attributes->get(self::ATTRIBUTE);

        if (! is_string($requestId)) {
            $requestId = Str::uuid()->toString();
            $request->attributes->set(self::ATTRIBUTE, $requestId);
        }

        return $requestId;
    }
}
