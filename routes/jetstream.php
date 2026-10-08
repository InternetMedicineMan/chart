<?php

use Illuminate\Support\Facades\Route;
use Laravel\Jetstream\Http\Controllers\Inertia\OtherBrowserSessionsController;
use Laravel\Jetstream\Http\Controllers\Inertia\UserProfileController;

Route::middleware(['auth', config('jetstream.auth_session')])->group(function () {
    Route::get('/user/profile', [UserProfileController::class, 'show'])->name('profile.show');
    Route::delete('/user/other-browser-sessions', [OtherBrowserSessionsController::class, 'destroy'])
        ->name('other-browser-sessions.destroy');
});
