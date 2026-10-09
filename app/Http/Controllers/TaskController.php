<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveTaskRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskCompletion;
use App\Services\TaskRecurrence;
use App\Services\WorkSetup;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    public function store(SaveTaskRequest $request, WorkSetup $setup): RedirectResponse
    {
        DB::transaction(function () use ($request, $setup) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $attributes = $this->attributes($request, $setup);
            $attributes = array_replace($attributes, app(TaskRecurrence::class)->attributes($request->user(), $attributes));
            Task::create($attributes + ['user_id' => $request->user()->id, 'source' => 'manual']);
        });

        return back()->with('message', 'Task saved.');
    }

    public function update(SaveTaskRequest $request, int $task, WorkSetup $setup): RedirectResponse
    {
        DB::transaction(function () use ($request, $task, $setup) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            // Lock the project before the task, matching project moves that update their children.
            $attributes = $this->attributes($request, $setup) + ['needs_review' => false];
            $record = Task::forUser($request->user())->lockForUpdate()->findOrFail($task);
            app(TaskCompletion::class)->assertRevision($record, $request->input('revision'));
            $recurrence = app(TaskRecurrence::class)->attributes($request->user(), $attributes, $record);
            if ($record->completed_at && $record->fill($recurrence)->isDirty()) {
                throw ValidationException::withMessages(['recurrence_rule' => 'Edit the open next occurrence to change or stop this repeat.']);
            }
            $record->update(array_replace($attributes, $recurrence));
        });

        return back()->with('message', 'Task updated.');
    }

    private function attributes(SaveTaskRequest $request, WorkSetup $setup): array
    {
        $attributes = $request->safe()->except('revision');
        $project = ! empty($attributes['project_id'])
            ? Project::forUser($request->user())->lockForUpdate()->findOrFail($attributes['project_id']) : null;
        $attributes['project_id'] = $project?->id;
        $attributes['domain_id'] = $project?->domain_id ?? ($attributes['domain_id'] ?? $setup->inbox($request->user())->id);

        return $attributes;
    }

    public function completion(Request $request, int $task): RedirectResponse
    {
        $data = $request->validate(['completed' => ['required', 'boolean'], 'revision' => ['nullable', 'integer', 'min:0']]);
        DB::transaction(function () use ($request, $task, $data) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $record = Task::forUser($request->user())->lockForUpdate()->findOrFail($task);
            if ((bool) $record->completed_at === (bool) $data['completed']) {
                return;
            }
            $completion = app(TaskCompletion::class);
            $completion->assertRevision($record, isset($data['revision']) ? (int) $data['revision'] : null);
            $data['completed'] ? $completion->complete($request->user(), $record, CarbonImmutable::now()) : $completion->reopen($request->user(), $record);
        });

        return back()->with('message', $data['completed'] ? 'Task completed.' : 'Task reopened.');
    }

    public function destroy(Request $request, int $task): RedirectResponse
    {
        DB::transaction(function () use ($request, $task) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $record = Task::forUser($request->user())->lockForUpdate()->findOrFail($task);
            $record->update(['revision' => $record->revision + 1]);
            $record->delete();
        });

        return back()->with('message', 'Task moved to Recently deleted.');
    }

    public function restore(Request $request, int $task): RedirectResponse
    {
        DB::transaction(function () use ($request, $task) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            Task::onlyTrashed()->forUser($request->user())->lockForUpdate()->findOrFail($task)->restore();
        });

        return back()->with('message', 'Task restored.');
    }
}
