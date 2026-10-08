<?php

use App\Http\Middleware\RequireTwoFactorAuthentication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (Request $request) {
    return $request->user() ? redirect()->route('dashboard') : Inertia::render('Home');
})->name('home');

Route::get('/up', fn () => response()->json(['status' => 'ok']))->name('health');

Route::middleware(['auth', config('jetstream.auth_session'), RequireTwoFactorAuthentication::class])
    ->group(function () {
        Route::get('/dashboard', fn () => Inertia::render('Dashboard'))->name('dashboard');
    });

require __DIR__.'/jetstream.php';
