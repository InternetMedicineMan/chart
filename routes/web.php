<?php

use App\Http\Controllers\DomainController;
use App\Http\Controllers\IdeaController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\WorkController;
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
        Route::get('/dashboard', [WorkController::class, 'dashboard'])->name('dashboard');
        Route::get('/bench', [WorkController::class, 'bench'])->name('bench');
        Route::get('/intake', [WorkController::class, 'intake'])->name('intake');
        Route::get('/ideas', [WorkController::class, 'ideas'])->name('ideas');
        Route::get('/more', fn () => Inertia::render('Work/More'))->name('more');
        Route::get('/settings/work', [WorkController::class, 'settings'])->name('work.settings');
        Route::put('/settings/timezone', [WorkController::class, 'timezone'])->name('work.timezone');
        Route::post('/domains', [DomainController::class, 'store'])->name('domains.store');
        Route::put('/domains/{domain}', [DomainController::class, 'update'])->name('domains.update');
        Route::delete('/domains/{domain}', [DomainController::class, 'destroy'])->name('domains.destroy');
        Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::get('/projects/{project}', [WorkController::class, 'project'])->name('projects.show');
        Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
        Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
        Route::patch('/tasks/{task}/completion', [TaskController::class, 'completion'])->name('tasks.completion');
        Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
        Route::post('/tasks/{task}/restore', [TaskController::class, 'restore'])->name('tasks.restore');
        Route::post('/ideas', [IdeaController::class, 'store'])->name('ideas.store');
        Route::put('/ideas/{idea}', [IdeaController::class, 'update'])->name('ideas.update');
        Route::patch('/ideas/{idea}/review', [IdeaController::class, 'review'])->name('ideas.review');
    });

require __DIR__.'/jetstream.php';
