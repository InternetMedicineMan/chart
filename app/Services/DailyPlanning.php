<?php

namespace App\Services;

use App\Models\DailyPlan;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DailyPlanning
{
    public function activeTasks(User $user): Builder
    {
        return Task::forUser($user)
            ->whereHas('domain', fn (Builder $query) => $query->forUser($user)->where('parked', false)->whereNull('archived_at'))
            ->where(fn (Builder $query) => $query->whereNull('project_id')->orWhereHas('project', fn (Builder $query) => $query->forUser($user)->where('lifecycle', 'active')));
    }

    public function today(User $user): array
    {
        $date = app(LocalDate::class)->today($user);
        $plan = DailyPlan::forUser($user)->whereDate('plan_date', $date)->first();
        $ids = $plan?->top_task_ids ?? [];
        $tasks = $this->activeTasks($user)->whereIn('id', $ids)->with(['domain', 'project'])->get()->keyBy('id');
        app(WaitTracking::class)->decorate($tasks, $user);

        $tomorrow = DailyPlan::forUser($user)->whereDate('plan_date', CarbonImmutable::parse($date)->addDay()->toDateString())->first();
        $tomorrowTasks = $this->activeTasks($user)->whereIn('id', $tomorrow?->top_task_ids ?? [])->get(['id', 'title'])->keyBy('id');

        return [
            'tomorrow_tasks' => collect($tomorrow?->top_task_ids ?? [])->map(fn ($id) => $tomorrowTasks->get($id))->filter()->values(),
            'plan_date' => $date,
            'revision' => $plan?->revision ?? 0,
            'tasks' => collect($ids)->map(fn ($id) => $tasks->get($id))->filter()->values(),
            'unavailable_count' => count($ids) - $tasks->count(),
            'tomorrow_focus' => $plan?->tomorrow_focus,
            'today_focus' => DailyPlan::forUser($user)->whereDate('plan_date', CarbonImmutable::parse($date)->subDay()->toDateString())->value('tomorrow_focus'),
        ];
    }

    public function save(User $user, array $data): void
    {
        DB::transaction(function () use ($user, $data) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($data['plan_date'] !== app(LocalDate::class)->today($user)) {
                throw ValidationException::withMessages(['plan_date' => 'The local day has changed. Reload the plan before saving.']);
            }
            DailyPlan::forUser($user)->firstOrCreate(['plan_date' => $data['plan_date']], [
                'user_id' => $user->id, 'top_task_ids' => [],
            ]);
            $plan = DailyPlan::forUser($user)->whereDate('plan_date', $data['plan_date'])->lockForUpdate()->firstOrFail();
            if ($plan->revision !== (int) $data['revision']) {
                throw ValidationException::withMessages(['revision' => 'This plan changed in another tab or device. Reload it before saving so those changes are preserved.']);
            }
            $ids = array_map('intval', $data['top_task_ids']);
            $tasks = $this->activeTasks($user)->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            if ($tasks->count() !== count($ids) || $tasks->contains(fn (Task $task) => ($task->completed_at || $task->waiting_on_person_id) && ! in_array($task->id, $plan->top_task_ids, true))) {
                throw ValidationException::withMessages(['top_task_ids' => 'Choose open tasks that are with you. A task may now be waiting, completed, deleted or parked; reload the plan to check.']);
            }
            $plan->update([
                'top_task_ids' => $ids,
                'tomorrow_focus' => filled($data['tomorrow_focus']) ? $data['tomorrow_focus'] : null,
                'revision' => $plan->revision + 1,
            ]);
        });
    }
}
