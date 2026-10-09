<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskStructure
{
    /** Called while holding the owner lock. Subtasks have one level and share their parent's home. */
    public function attributes(User $user, array $data, ?Task $task = null): array
    {
        foreach (['parent_task_id', 'milestone_id', 'touch_target_type', 'touch_target_id'] as $field) {
            $data[$field] = array_key_exists($field, $data) ? $data[$field] : $task?->$field;
        }
        if ($data['parent_task_id']) {
            $parent = Task::forUser($user)->find($data['parent_task_id']);
            if (! $parent || $parent->parent_task_id || ($parent->completed_at && (int) $task?->parent_task_id !== $parent->id) || $parent->id === $task?->id || ($task && $task->subtasks()->withTrashed()->exists())) {
                throw ValidationException::withMessages(['parent_task_id' => 'Choose an open top-level task. A task with subtasks cannot itself become a subtask.']);
            }
            if (! Domain::forUser($user)->whereNull('archived_at')->whereKey($parent->domain_id)->exists() || ($parent->project_id && ! Project::forUser($user)->whereKey($parent->project_id)->exists())) {
                throw ValidationException::withMessages(['parent_task_id' => 'Restore the parent’s project or domain before adding or moving subtasks.']);
            }
            if (filled(array_key_exists('recurrence_rule', $data) ? $data['recurrence_rule'] : $task?->recurrence_rule)) {
                throw ValidationException::withMessages(['recurrence_rule' => 'Set the repeat on the parent task. Its subtasks repeat with it.']);
            }
            $data['domain_id'] = $parent->domain_id;
            $data['project_id'] = $parent->project_id;
            $data['milestone_id'] = $parent->milestone_id;
        }
        if ($data['milestone_id']) {
            $milestone = Milestone::forUser($user)->where('project_id', $data['project_id'])->find($data['milestone_id']);
            if (! $milestone) {
                throw ValidationException::withMessages(['milestone_id' => 'Choose a milestone from this task’s project.']);
            }
        }
        if ($data['touch_target_type'] || $data['touch_target_id']) {
            $class = match ($data['touch_target_type']) {
                'domain' => Domain::class, 'project' => Project::class, default => null
            };
            if (! $class || ! $data['touch_target_id'] || ! $class::forUser($user)->when($class === Domain::class, fn ($q) => $q->whereNull('archived_at'))->find($data['touch_target_id'])) {
                throw ValidationException::withMessages(['touch_target_id' => 'Choose an available domain or project for the extra touch.']);
            }
        }

        return $data;
    }

    public function syncChildren(User $user, Task $task): void
    {
        Task::withTrashed()->forUser($user)->where('parent_task_id', $task->id)->update([
            'domain_id' => $task->domain_id, 'project_id' => $task->project_id, 'milestone_id' => $task->milestone_id, 'revision' => DB::raw('revision + 1'),
        ]);
    }

    public function assertCanComplete(User $user, Task $task): void
    {
        if ($task->subtasks()->forUser($user)->whereNull('completed_at')->exists()) {
            throw ValidationException::withMessages(['completion' => 'Finish all subtasks before completing this task.']);
        }
    }

    public function assertCanReopen(User $user, Task $task): void
    {
        if ($task->parent_task_id && Task::withTrashed()->forUser($user)->whereKey($task->parent_task_id)->where(fn ($q) => $q->whereNotNull('completed_at')->orWhereNotNull('deleted_at'))->exists()) {
            throw ValidationException::withMessages(['completion' => 'Reopen or restore the parent task before reopening a subtask.']);
        }
    }
}
