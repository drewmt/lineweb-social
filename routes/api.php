<?php

use App\Http\Controllers\Api\V1\CurrentProfileController;
use App\Http\Middleware\AssignApiRequestId;
use App\Http\Middleware\RequireBearerAccessToken;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware([
        AssignApiRequestId::class,
        'auth:sanctum',
        RequireBearerAccessToken::class,
        'verified',
        'abilities:profile:read',
        'throttle:api-read',
    ])
    ->group(function (): void {
        Route::get('me', CurrentProfileController::class)->name('api.v1.me');
    });
