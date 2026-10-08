<?php

use App\Http\Controllers\DeviceCaptureController;
use App\Http\Middleware\AuthenticateCapture;
use Illuminate\Support\Facades\Route;

Route::post('/capture', DeviceCaptureController::class)
    ->middleware(AuthenticateCapture::class)
    ->name('capture.api');
