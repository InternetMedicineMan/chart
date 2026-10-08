<?php

namespace App\Http\Controllers;

use App\Enums\ProjectLifecycle;
use App\Http\Requests\SaveProjectRequest;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
            $record = Project::forUser($request->user())->lockForUpdate()->findOrFail($project);
            $attributes = $request->validated();
            $attributes['completed_at'] = $attributes['lifecycle'] === ProjectLifecycle::Done->value ? ($record->completed_at ?? now()) : null;
            $record->update($attributes);
            // Include deleted tasks so restoring one cannot put it under the project's former domain.
            Task::withTrashed()->forUser($request->user())->where('project_id', $record->id)->update(['domain_id' => $record->domain_id]);
        });

        return back()->with('message', 'Project updated.');
    }
}
