<?php

namespace App\Http\Controllers;

use App\Enums\ProjectLifecycle;
use App\Http\Requests\SaveProjectRequest;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\WaitTracking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProjectController extends Controller
{
    public function store(SaveProjectRequest $request): RedirectResponse
    {
        $project = Project::create($request->validated() + [
            'user_id' => $request->user()->id, 'slug' => Str::uuid()->toString(),
            'completed_at' => $request->lifecycle === 'done' ? now() : null,
        ]);

        return to_route('projects.show', $project)->with('message', 'Project created.');
    }

    public function update(SaveProjectRequest $request, int $project): RedirectResponse
    {
        DB::transaction(function () use ($request, $project) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $record = Project::forUser($request->user())->lockForUpdate()->findOrFail($project);
            $attributes = $request->validated() + ['needs_review' => false];
            $attributes['completed_at'] = $attributes['lifecycle'] === ProjectLifecycle::Done->value ? ($record->completed_at ?? now()) : null;
            $record->update($attributes);
            if (in_array($record->lifecycle, [ProjectLifecycle::Done, ProjectLifecycle::Dropped], true)) {
                app(WaitTracking::class)->clear($record);
            }
            // Include deleted tasks so restoring one cannot put it under the project's former domain.
            Task::withTrashed()->forUser($request->user())->where('project_id', $record->id)->update(['domain_id' => $record->domain_id, 'revision' => DB::raw('revision + 1')]);
        });

        return back()->with('message', 'Project updated.');
    }

    public function destroy(Request $request, int $project): RedirectResponse
    {
        DB::transaction(function () use ($request, $project) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $record = Project::withTrashed()->forUser($request->user())->lockForUpdate()->findOrFail($project);
            if ($record->trashed()) {
                return;
            }
            if (ActivityLog::forUser($request->user())->where('subject_type', 'project')->where('subject_id', $record->id)->exists()) {
                throw ValidationException::withMessages(['project' => 'This project has activity history. Keep it as Done or Dropped, or remove its activity entries before deleting it.']);
            }
            if ($record->milestones()->exists()) {
                throw ValidationException::withMessages(['project' => 'Remove this project’s milestones before deleting it, or keep the project as Done or Dropped.']);
            }
            if ($record->tasks()->withTrashed()->exists()) {
                throw ValidationException::withMessages(['project' => 'Move this project’s tasks to another project or choose No project first. Tasks in Recently deleted must be restored and moved too.']);
            }
            $record->delete();
        });

        return to_route('bench', ['project_status' => 'trash'])->with('message', 'Project moved to Recently deleted.');
    }

    public function restore(Request $request, int $project): RedirectResponse
    {
        DB::transaction(function () use ($request, $project) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $record = Project::withTrashed()->forUser($request->user())->lockForUpdate()->findOrFail($project);
            if ($record->trashed()) {
                $record->restore();
            }
        });

        return back()->with('message', 'Project restored. Someday projects return to Ideas.');
    }
}
