<?php

namespace App\Services;

use App\Models\ActionLog;
use App\Models\Capture;
use App\Models\Domain;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CaptureStructureActions
{
    public const TYPES = ['complete_milestone', 'assign_milestone'];

    public function handles(array $data, array $chosen = []): bool
    {
        return in_array($data['type'], self::TYPES, true) || ($data['type'] === 'create_task' && (filled($data['parent_ref'] ?? null) || filled($data['milestone_ref'] ?? null) || ! empty($chosen['parent_task_id']) || ! empty($chosen['milestone_id'])));
    }

    private function exact(?string $reference, Collection $records, string $kind, ?int $chosen = null): int
    {
        $matches = $chosen ? $records->where('id', $chosen) : $records->filter(fn ($record) => Str::lower(Str::squish($record->name)) === Str::lower(Str::squish($reference ?? '')));
        if ($matches->count() !== 1) {
            throw ValidationException::withMessages([$kind => "Choose a unique existing {$kind}. Automatic filing requires an exact name match."]);
        }

        return $matches->first()->id;
    }

    private function current(Task|Milestone $record, Capture $capture, array $chosen, string $revision, bool $reviewed): void
    {
        if ($reviewed ? (! isset($chosen[$revision]) || (int) $chosen[$revision] !== $record->revision) : $record->updated_at->gte($capture->client_captured_at->startOfSecond())) {
            throw ValidationException::withMessages(['revision' => 'This work changed. Reload and review the current task or milestone.']);
        }
    }

    public function prepare(User $user, Capture $capture, array $data, array $chosen = [], bool $reviewed = false): array
    {
        if ((! $reviewed && $data['confidence'] < .8) || $capture->client_captured_at->isFuture()) {
            throw ValidationException::withMessages(['action' => 'Review this structure change and its recording time before filing.']);
        }
        $domains = Domain::forUser($user)->where('parked', false)->whereNull('archived_at')->get();
        $domainId = filled($data['domain_ref'] ?? null) || ! empty($chosen['domain_id']) ? $this->exact($data['domain_ref'] ?? null, $domains, 'domain', $chosen['domain_id'] ?? null) : null;
        $projects = Project::forUser($user)->where('lifecycle', 'active')->whereIn('domain_id', $domains->pluck('id'))->when($domainId, fn ($q) => $q->where('domain_id', $domainId))->get();
        $projectId = filled($data['project_ref'] ?? null) || ! empty($chosen['project_id']) ? $this->exact($data['project_ref'] ?? null, $projects, 'project', $chosen['project_id'] ?? null) : null;
        $task = null;
        $parent = $data['type'] === 'create_task' && (filled($data['parent_ref'] ?? null) || ! empty($chosen['parent_task_id']));
        if ($parent || $data['type'] === 'assign_milestone') {
            $tasks = app(DailyPlanning::class)->activeTasks($user)->whereNull('completed_at')->whereNull('parent_task_id')->when($projectId, fn ($q) => $q->where('project_id', $projectId))->when($domainId, fn ($q) => $q->where('domain_id', $domainId))->get(['id', 'title as name']);
            $taskId = $this->exact($data[$parent ? 'parent_ref' : 'task_ref'] ?? null, $tasks, $parent ? 'parent task' : 'task', $chosen[$parent ? 'parent_task_id' : 'task_id'] ?? null);
            $task = Task::forUser($user)->findOrFail($taskId);
            $this->current($task, $capture, $chosen, $parent ? 'parent_revision' : 'task_revision', $reviewed);
            $projectId = $task->project_id;
        }
        $milestone = null;
        if (in_array($data['type'], self::TYPES, true) || filled($data['milestone_ref'] ?? null) || ! empty($chosen['milestone_id'])) {
            if (! $projectId) {
                throw ValidationException::withMessages(['project' => 'Choose the existing project for this milestone.']);
            }
            $milestones = Milestone::forUser($user)->where('project_id', $projectId)->whereNull('completed_at')->get(['id', 'title as name']);
            $id = $this->exact($data['milestone_ref'] ?? null, $milestones, 'milestone', $chosen['milestone_id'] ?? null);
            $milestone = Milestone::forUser($user)->findOrFail($id);
            $this->current($milestone, $capture, $chosen, 'milestone_revision', $reviewed);
            if ($parent && $task->milestone_id !== $milestone->id) {
                throw ValidationException::withMessages(['milestone' => 'Subtasks inherit their parent’s milestone. Change the parent’s milestone first.']);
            }
        }

        if ($data['type'] === 'assign_milestone' && ! $reviewed && Task::withTrashed()->forUser($user)->where('parent_task_id', $task->id)->where('updated_at', '>=', $capture->client_captured_at->startOfSecond())->exists()) {
            throw ValidationException::withMessages(['revision' => 'A subtask changed after recording. Review the parent’s milestone assignment.']);
        }

        return [$task, $milestone, $projectId];
    }

    public function execute(User $user, Capture $capture, array $data, array $chosen, bool $reviewed): array
    {
        [$task, $milestone, $projectId] = $this->prepare($user, $capture, $data, $chosen, $reviewed);
        if ($data['type'] === 'create_task') {
            $attributes = app(TaskStructure::class)->attributes($user, [
                'domain_id' => $task?->domain_id ?? Project::forUser($user)->findOrFail($projectId)->domain_id,
                'project_id' => $projectId, 'parent_task_id' => $task?->id, 'milestone_id' => $milestone?->id,
            ]);
            $target = Task::create($attributes + ['user_id' => $user->id, 'title' => $data['title'], 'notes' => $data['body'] ?? null, 'due_date' => $data['due_date'] ?? null, 'due_time' => $data['due_time'] ?? null, 'priority' => $data['priority'] ?? 4]);

            return [$target, 'task', null];
        }
        if ($data['type'] === 'complete_milestone') {
            $before = $milestone->getRawOriginal();
            $milestone->update(['completed_at' => $capture->client_captured_at, 'revision' => $milestone->revision + 1]);

            return [$milestone, 'milestone', $before];
        }
        $children = Task::withTrashed()->forUser($user)->where('parent_task_id', $task->id)->get();
        $before = ['record' => $task->getRawOriginal(), 'children_before' => $children->map->getRawOriginal()->all()];
        $task->update(['milestone_id' => $milestone->id, 'revision' => $task->revision + 1]);
        app(TaskStructure::class)->syncChildren($user, $task);
        $before['children_after'] = Task::withTrashed()->forUser($user)->where('parent_task_id', $task->id)->orderBy('id')->get()->map->getRawOriginal()->all();

        return [$task, 'task', $before];
    }

    public function undo(User $user, ActionLog $log): void
    {
        $class = $log->target_type === 'milestone' ? Milestone::class : Task::class;
        $target = $class::withTrashed()->forUser($user)->find($log->target_id);
        if (! $target || $target->getRawOriginal() != $log->after_snapshot) {
            throw ValidationException::withMessages(['undo' => 'This record changed after capture. Edit it directly to preserve newer work.']);
        }
        if ($log->action_type === 'complete_milestone') {
            $target->update(['completed_at' => $log->before_snapshot['completed_at'], 'revision' => $target->revision + 1]);

            return;
        }
        $children = Task::withTrashed()->forUser($user)->where('parent_task_id', $target->id)->orderBy('id')->get();
        if ($children->map->getRawOriginal()->all() != $log->before_snapshot['children_after']) {
            throw ValidationException::withMessages(['undo' => 'The subtasks changed after assignment. Edit the milestone directly to preserve newer work.']);
        }
        $oldId = $log->before_snapshot['record']['milestone_id'];
        if ($oldId && ! Milestone::forUser($user)->where('project_id', $target->project_id)->whereKey($oldId)->exists()) {
            throw ValidationException::withMessages(['undo' => 'The previous milestone is unavailable. Choose a milestone directly.']);
        }
        $target->update(['milestone_id' => $oldId, 'revision' => $target->revision + 1]);
        foreach ($children as $child) {
            $old = collect($log->before_snapshot['children_before'])->firstWhere('id', $child->id);
            $child->update(['milestone_id' => $old['milestone_id'], 'revision' => $child->revision + 1]);
        }
    }
}
