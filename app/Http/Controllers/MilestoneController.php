<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveMilestoneRequest;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MilestoneController extends Controller
{
    public function store(SaveMilestoneRequest $request, int $project): RedirectResponse
    {
        return $this->save($request, $project);
    }

    public function update(SaveMilestoneRequest $request, int $project, int $milestone): RedirectResponse
    {
        return $this->save($request, $project, $milestone);
    }

    private function save(SaveMilestoneRequest $request, int $project, ?int $milestone = null): RedirectResponse
    {
        DB::transaction(function () use ($request, $project, $milestone) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            Project::forUser($request->user())->findOrFail($project);
            $record = $milestone ? Milestone::forUser($request->user())->where('project_id', $project)->lockForUpdate()->findOrFail($milestone) : new Milestone(['user_id' => $request->user()->id, 'project_id' => $project, 'revision' => 0]);
            if ($record->revision !== $request->integer('revision')) {
                throw ValidationException::withMessages(['revision' => 'This milestone changed. Reload before saving.']);
            }
            $record->fill($request->safe()->except(['completed', 'revision']));
            $record->completed_at = $request->boolean('completed') ? ($record->completed_at ?? now()) : null;
            $record->revision++;
            $record->save();
        });

        return back()->with('message', 'Milestone saved.');
    }

    public function destroy(SaveMilestoneRequest $request, int $project, int $milestone): RedirectResponse
    {
        DB::transaction(function () use ($request, $project, $milestone) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            Project::forUser($request->user())->findOrFail($project);
            $record = Milestone::forUser($request->user())->where('project_id', $project)->lockForUpdate()->findOrFail($milestone);
            if ($record->revision !== $request->integer('revision')) {
                throw ValidationException::withMessages(['revision' => 'This milestone changed. Reload before removing it.']);
            }
            if (Task::withTrashed()->forUser($request->user())->where('milestone_id', $record->id)->exists()) {
                throw ValidationException::withMessages(['milestone' => 'Move its tasks to another milestone or choose No milestone first, including tasks in Recently deleted.']);
            }
            $record->delete();
        });

        return back()->with('message', 'Milestone removed.');
    }
}
