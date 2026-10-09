<?php

namespace App\Services;

use App\Models\DailyPlan;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskCompletion
{
    /** All callers hold the owner and task locks in a transaction. */
    public function complete(User $user, Task $task, CarbonImmutable $occurred): void
    {
        if ($task->completed_at) {
            return;
        }
        app(TaskStructure::class)->assertCanComplete($user, $task);
        $before = $task->getRawOriginal();
        $touches = DB::table('work_touches')->where('user_id', $user->id)->where('origin_type', 'completion')->where('origin_id', $task->id)->get()->map(fn ($row) => (array) $row)->all();
        // Offline uploads advance beyond today too, without fabricating intervening completions.
        $next = app(TaskRecurrence::class)->next($task, CarbonImmutable::now());
        $task->update(['completed_at' => $occurred]);
        app(WaitTracking::class)->clear($task);
        app(ActivityTracking::class)->completed($user, $task);
        $successor = null;
        $childSnapshots = [];
        if ($next) {
            $successor = Task::create(array_intersect_key($before, array_flip(['user_id', 'domain_id', 'project_id', 'milestone_id', 'touch_target_type', 'touch_target_id', 'title', 'notes', 'priority', 'due_time', 'source', 'recurrence_rule', 'recurrence_anchor', 'recurrence_timezone']))
                + $next + ['recurrence_parent_id' => $task->id]);
            foreach ($task->subtasks()->forUser($user)->get() as $child) {
                $attributes = $child->only(['user_id', 'domain_id', 'project_id', 'milestone_id', 'title', 'notes', 'priority', 'due_time', 'touch_target_type', 'touch_target_id', 'source']);
                $offset = $child->due_date ? (int) $task->due_date->diffInDays($child->due_date, false) : null;
                $copy = Task::create($attributes + ['parent_task_id' => $successor->id, 'due_date' => $offset !== null ? CarbonImmutable::parse($next['due_date'])->addDays($offset)->toDateString() : null]);
                $childSnapshots[] = $copy->fresh()->getRawOriginal();
            }
        }
        DB::table('task_completions')->insert(['task_id' => $task->id, 'user_id' => $user->id,
            'before_snapshot' => json_encode($before, JSON_THROW_ON_ERROR), 'after_snapshot' => json_encode($task->fresh()->getRawOriginal(), JSON_THROW_ON_ERROR),
            'successor_children' => json_encode($childSnapshots, JSON_THROW_ON_ERROR),
            'previous_touches' => json_encode($touches, JSON_THROW_ON_ERROR), 'successor_id' => $successor?->id,
            'successor_snapshot' => $successor ? json_encode($successor->fresh()->getRawOriginal(), JSON_THROW_ON_ERROR) : null]);
    }

    /** A manual reopen retains historical touch; capture undo reverses that exact completion's effects. */
    public function reopen(User $user, Task $task, bool $undo = false): void
    {
        if (! $task->completed_at) {
            return;
        }
        app(TaskStructure::class)->assertCanReopen($user, $task);
        $history = DB::table('task_completions')->where('user_id', $user->id)->where('task_id', $task->id)->lockForUpdate()->first();
        if ($undo && (! $history || $task->getRawOriginal() != json_decode($history->after_snapshot, true))) {
            throw ValidationException::withMessages(['undo' => 'This completion changed. Edit the task directly to preserve newer work.']);
        }
        if ($history?->successor_id) {
            $next = Task::withTrashed()->forUser($user)->lockForUpdate()->find($history->successor_id);
            $planned = DailyPlan::forUser($user)->get(['top_task_ids'])->contains(fn ($plan) => in_array($history->successor_id, $plan->top_task_ids, true));
            if (! $next || $next->getRawOriginal() != json_decode($history->successor_snapshot, true) || $planned) {
                throw ValidationException::withMessages(['completion' => 'The next occurrence has been changed or added to a daily plan. Keep it intact; edit that occurrence directly.']);
            }
            $expectedChildren = collect(json_decode($history->successor_children ?? '[]', true))->keyBy('id');
            $children = $next->subtasks()->withTrashed()->forUser($user)->lockForUpdate()->get();
            $plannedIds = DailyPlan::forUser($user)->get(['top_task_ids'])->flatMap(fn ($plan) => $plan->top_task_ids);
            if ($children->count() !== $expectedChildren->count() || $children->contains(fn ($child) => $child->getRawOriginal() != $expectedChildren->get($child->id) || $plannedIds->contains($child->id))) {
                throw ValidationException::withMessages(['completion' => 'The next occurrence’s subtasks changed or were planned. Keep them intact and edit that occurrence directly.']);
            }
            foreach ($children as $child) {
                $child->forceDelete();
            }
            // Only untouched generated records are removed, never user-entered work.
            $next->forceDelete();
        }
        if ($undo) {
            $before = json_decode($history->before_snapshot, true);
            $task->fill(array_intersect_key($before, array_flip(['completed_at', 'waiting_on_person_id', 'waiting_since', 'wait_expected_by'])));
            $task->wait_revision++;
            app(ActivityTracking::class)->restoreCompletionTouches($user, $task->id, json_decode($history->previous_touches, true));
        } else {
            $task->completed_at = null;
        }
        $task->save();
        DB::table('task_completions')->where('user_id', $user->id)->where('task_id', $task->id)->delete();
    }

    public function assertRevision(Task $task, ?int $revision): void
    {
        if (($revision !== null && $revision !== $task->revision) || ($task->recurrence_rule && $revision === null)) {
            throw ValidationException::withMessages(['revision' => 'This task changed. Reload it before saving or completing.']);
        }
    }
}
