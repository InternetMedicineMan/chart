<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveTaskRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\ActivityTracking;
use App\Services\WaitTracking;
use App\Services\WorkSetup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    public function store(SaveTaskRequest $request, WorkSetup $setup): RedirectResponse
    {
        DB::transaction(function () use ($request, $setup) {
            Task::create($this->attributes($request, $setup) + ['user_id' => $request->user()->id, 'source' => 'manual']);
        });

        return back()->with('message', 'Task saved.');
    }

    public function update(SaveTaskRequest $request, int $task, WorkSetup $setup): RedirectResponse
    {
        DB::transaction(function () use ($request, $task, $setup) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            // Lock the project before the task, matching project moves that update their children.
            $attributes = $this->attributes($request, $setup) + ['needs_review' => false];
            Task::forUser($request->user())->lockForUpdate()->findOrFail($task)->update($attributes);
        });

        return back()->with('message', 'Task updated.');
    }

    private function attributes(SaveTaskRequest $request, WorkSetup $setup): array
    {
        $attributes = $request->validated();
        $project = ! empty($attributes['project_id'])
            ? Project::forUser($request->user())->lockForUpdate()->findOrFail($attributes['project_id']) : null;
        $attributes['project_id'] = $project?->id;
        $attributes['domain_id'] = $project?->domain_id ?? ($attributes['domain_id'] ?? $setup->inbox($request->user())->id);

        return $attributes;
    }

    public function completion(Request $request, int $task): RedirectResponse
    {
        $data = $request->validate(['completed' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $task, $data) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $record = Task::forUser($request->user())->lockForUpdate()->findOrFail($task);
            if ((bool) $record->completed_at === (bool) $data['completed']) {
                return;
            }
            $record->update(['completed_at' => $data['completed'] ? now() : null]);
            if ($data['completed']) {
                app(WaitTracking::class)->clear($record);
                app(ActivityTracking::class)->completed($request->user(), $record);
            }
        });

        return back()->with('message', $data['completed'] ? 'Task completed.' : 'Task reopened.');
    }

    public function destroy(Request $request, int $task): RedirectResponse
    {
        Task::forUser($request->user())->findOrFail($task)->delete();

        return back()->with('message', 'Task moved to Recently deleted.');
    }

    public function restore(Request $request, int $task): RedirectResponse
    {
        Task::onlyTrashed()->forUser($request->user())->findOrFail($task)->restore();

        return back()->with('message', 'Task restored.');
    }
}
