<?php

use App\Http\Controllers\Api\V1\CurrentProfileController;
use App\Http\Controllers\Api\V1\FeedController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PostCommentController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\PostMediaController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\SpaceController;
use App\Http\Controllers\Api\V1\SpaceIndexController;
use App\Http\Middleware\AssignApiRequestId;
use App\Http\Middleware\RequireBearerAccessToken;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware([
        AssignApiRequestId::class,
        'auth:sanctum',
        RequireBearerAccessToken::class,
        'verified',
        'throttle:api-read',
    ])
    ->group(function (): void {
        Route::get('me', CurrentProfileController::class)
            ->middleware('abilities:profile:read')
            ->name('api.v1.me');
        Route::get('profiles/{profile:handle}', ProfileController::class)
            ->middleware('abilities:profiles:read')
            ->name('api.v1.profiles.show');
        Route::get('spaces', SpaceIndexController::class)
            ->middleware('abilities:spaces:read')
            ->name('api.v1.spaces.index');
        Route::get('spaces/{space:slug}', SpaceController::class)
            ->middleware('abilities:spaces:read')
            ->name('api.v1.spaces.show');
        Route::get('feed', FeedController::class)
            ->middleware('abilities:feed:read')
            ->name('api.v1.feed');
        Route::get('posts/{post}/media', PostMediaController::class)
            ->middleware('abilities:feed:read')
            ->name('api.v1.posts.media');
        Route::get('posts/{post}', PostController::class)
            ->middleware('abilities:feed:read')
            ->name('api.v1.posts.show');
        Route::get('posts/{post}/comments', PostCommentController::class)
            ->middleware('abilities:feed:read')
            ->name('api.v1.posts.comments');
        Route::get('notifications', NotificationController::class)
            ->middleware('abilities:notifications:read')
            ->name('api.v1.notifications');
        Route::patch('notifications/read-all', [NotificationController::class, 'readAll'])
            ->middleware('abilities:notifications:write')
            ->name('api.v1.notifications.read-all');
        Route::patch('notifications/{notification}/read', [NotificationController::class, 'readOne'])
            ->middleware('abilities:notifications:write')
            ->name('api.v1.notifications.read');
    });
