<?php

use App\Http\Controllers\CaptureController;
use App\Http\Controllers\CaptureTokenController;
use App\Http\Controllers\DailyPlanController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\IdeaController;
use App\Http\Controllers\NotificationFeedController;
use App\Http\Controllers\ParserPreviewController;
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
        Route::get('/daily-plan/tasks', [DailyPlanController::class, 'tasks'])->name('daily-plan.tasks');
        Route::put('/daily-plan', [DailyPlanController::class, 'update'])->name('daily-plan.update');
        Route::get('/bench', [WorkController::class, 'bench'])->name('bench');
        Route::get('/intake', [WorkController::class, 'intake'])->name('intake');
        Route::get('/notifications', [NotificationFeedController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/read-all', [NotificationFeedController::class, 'readAll'])->name('notifications.read-all');
        Route::patch('/notifications/{notification}', [NotificationFeedController::class, 'update'])->name('notifications.update');
        Route::post('/captures', [CaptureController::class, 'store'])->middleware('throttle:30,1')->name('captures.store');
        Route::get('/captures/{capture}', [CaptureController::class, 'show'])->name('captures.show');
        Route::post('/captures/{capture}/retry', [CaptureController::class, 'retry'])->middleware('throttle:10,1')->name('captures.retry');
        Route::put('/capture-items/{item}', [CaptureController::class, 'resolve'])->name('capture-items.resolve');
        Route::post('/capture-items/{item}/retry', [CaptureController::class, 'retryItem'])->name('capture-items.retry');
        Route::post('/capture-items/{item}/undo', [CaptureController::class, 'undo'])->name('capture-items.undo');
        Route::get('/ideas', [WorkController::class, 'ideas'])->name('ideas');
        Route::get('/more', fn () => Inertia::render('Work/More'))->name('more');
        Route::get('/settings/work', [WorkController::class, 'settings'])->name('work.settings');
        Route::post('/settings/capture-tokens', [CaptureTokenController::class, 'store'])->middleware('throttle:5,1')->name('capture-tokens.store');
        Route::delete('/settings/capture-tokens/{token}', [CaptureTokenController::class, 'destroy'])->name('capture-tokens.destroy');
        Route::post('/settings/parser/preview', ParserPreviewController::class)->middleware('throttle:5,1')->name('parser.preview');
        Route::put('/settings/timezone', [WorkController::class, 'timezone'])->name('work.timezone');
        Route::post('/domains', [DomainController::class, 'store'])->name('domains.store');
        Route::put('/domains/{domain}', [DomainController::class, 'update'])->name('domains.update');
        Route::delete('/domains/{domain}', [DomainController::class, 'destroy'])->name('domains.destroy');
        Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::get('/projects/{project}', [WorkController::class, 'project'])->name('projects.show');
        Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
        Route::post('/projects/{project}/restore', [ProjectController::class, 'restore'])->name('projects.restore');
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
