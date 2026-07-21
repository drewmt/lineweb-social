<?php

use App\Http\Controllers\Settings\ApiTokenController;
use App\Http\Controllers\Settings\NotificationPreferencesController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SafetyController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::post('settings/api-tokens', [ApiTokenController::class, 'store'])
        ->middleware([RequirePassword::class, 'throttle:api-token-management'])
        ->name('api-tokens.store');
    Route::delete('settings/api-tokens', [ApiTokenController::class, 'destroyAll'])
        ->middleware([RequirePassword::class, 'throttle:api-token-management'])
        ->name('api-tokens.destroy-all');
    Route::delete('settings/api-tokens/{apiToken}', [ApiTokenController::class, 'destroy'])
        ->whereNumber('apiToken')
        ->middleware([RequirePassword::class, 'throttle:api-token-management'])
        ->name('api-tokens.destroy');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
    Route::get('settings/safety', SafetyController::class)->name('safety.edit');
    Route::get('settings/notifications', [NotificationPreferencesController::class, 'edit'])
        ->name('notification-preferences.edit');
    Route::patch('settings/notifications', [NotificationPreferencesController::class, 'update'])
        ->name('notification-preferences.update');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
