<?php

namespace App\Http\Responses;

use App\Exceptions\InvalidApiCursorException;
use App\Http\Middleware\AssignApiRequestId;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class ApiErrorResponse
{
    public static function from(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        if ($exception instanceof ValidationException) {
            return self::make(
                $request,
                'The request could not be completed.',
                'validation_failed',
                422,
                $exception->errors(),
            );
        }

        if ($exception instanceof InvalidApiCursorException) {
            return self::make(
                $request,
                'The cursor is invalid for this request.',
                'invalid_cursor',
                400,
            );
        }

        if ($exception instanceof AuthenticationException) {
            return self::make($request, 'Authentication is required.', 'unauthenticated', 401);
        }

        if ($exception instanceof AuthorizationException) {
            return self::make($request, 'This action is not allowed.', 'forbidden', 403);
        }

        if ($exception instanceof ModelNotFoundException || $exception instanceof NotFoundHttpException) {
            return self::make($request, 'The requested resource was not found.', 'not_found', 404);
        }

        if ($exception instanceof ThrottleRequestsException) {
            return self::make(
                $request,
                'Too many requests. Try again later.',
                'rate_limited',
                429,
                headers: $exception->getHeaders(),
            );
        }

        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();

            return self::make(
                $request,
                self::messageFor($status),
                self::codeFor($status),
                $status,
                headers: $exception->getHeaders(),
            );
        }

        return self::make(
            $request,
            'The server could not complete the request.',
            'server_error',
            500,
        );
    }

    /**
     * @param  array<string, list<string>>  $errors
     * @param  array<string, string|int>  $headers
     */
    private static function make(
        Request $request,
        string $message,
        string $code,
        int $status,
        array $errors = [],
        array $headers = [],
    ): JsonResponse {
        $requestId = AssignApiRequestId::for($request);
        $payload = [
            'message' => $message,
            'code' => $code,
            'request_id' => $requestId,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return response()
            ->json($payload, $status, $headers)
            ->header('X-Request-ID', $requestId);
    }

    private static function codeFor(int $status): string
    {
        return match ($status) {
            400 => 'invalid_request',
            401 => 'unauthenticated',
            403 => 'forbidden',
            404 => 'not_found',
            405 => 'method_not_allowed',
            422 => 'validation_failed',
            429 => 'rate_limited',
            default => $status >= 500 ? 'server_error' : 'request_failed',
        };
    }

    private static function messageFor(int $status): string
    {
        return match ($status) {
            400 => 'The request could not be completed.',
            401 => 'Authentication is required.',
            403 => 'This action is not allowed.',
            404 => 'The requested resource was not found.',
            405 => 'This request method is not supported.',
            429 => 'Too many requests. Try again later.',
            default => $status >= 500
                ? 'The server could not complete the request.'
                : 'The request could not be completed.',
        };
    }
}
