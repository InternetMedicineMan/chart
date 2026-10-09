<?php

namespace App\Services;

use App\Models\ActionLog;
use App\Models\Capture;
use App\Models\DailyPlan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CapturePlanActions
{
    public const TYPES = ['set_top3', 'set_tomorrow_focus'];

    /** Resolve and validate without writes; execution also holds the owner lock. */
    public function prepare(User $user, Capture $capture, array $data, array $chosen = [], bool $reviewed = false): array
    {
        $dates = app(LocalDate::class);
        $today = $dates->today($user);
        $tomorrow = CarbonImmutable::parse($today)->addDay()->toDateString();
        if ($capture->client_captured_at->isFuture()) {
            throw ValidationException::withMessages(['plan_date' => 'The recording time is in the future. Review its time before changing a plan.']);
        }
        $targetDate = $data['plan_date'];
        $focus = $data['type'] === 'set_tomorrow_focus';
        if ((! $reviewed && ($capture->timezone !== $dates->timezone($user) || $capture->client_captured_at->setTimezone($capture->timezone)->toDateString() !== $today))
            || ! in_array($targetDate, $focus ? [$tomorrow] : [$today, $tomorrow], true)) {
            throw ValidationException::withMessages(['plan_date' => 'The recording day or timezone changed. Review this against today’s plan before applying it.']);
        }
        if (! $reviewed && $data['confidence'] < .8) {
            throw ValidationException::withMessages(['plan' => 'Review this plan before replacing it.']);
        }
        $storageDate = $focus ? $today : $targetDate;
        $plan = DailyPlan::forUser($user)->whereDate('plan_date', $storageDate)->first();
        if ($reviewed) {
            if (! isset($chosen['plan_revision']) || (int) $chosen['plan_revision'] !== ($plan?->revision ?? 0)) {
                throw ValidationException::withMessages(['revision' => 'This plan changed while you were reviewing. Reload the capture before replacing it.']);
            }
        } elseif ($plan && $plan->updated_at->gte($capture->client_captured_at->startOfSecond())) {
            // Separate fields in one brain dump may apply together, but never overwrite an intervening edit.
            $sibling = $capture->id ? ActionLog::forUser($user)->where('capture_id', $capture->id)->where('target_type', 'daily_plan')->where('target_id', $plan->id)->where('status', 'ok')->latest('id')->first() : null;
            $sameField = $capture->id && ActionLog::forUser($user)->where('capture_id', $capture->id)->where('target_type', 'daily_plan')->where('target_id', $plan->id)->where('action_type', $data['type'])->exists();
            if (! $sibling || $sameField || $plan->getRawOriginal() != $sibling->after_snapshot) {
                throw ValidationException::withMessages(['revision' => 'This plan changed since recording. Review the current plan before replacing it.']);
            }
        }
        $ids = [];
        if (! $focus) {
            $tasks = app(DailyPlanning::class)->activeTasks($user)->whereNull('completed_at')->whereNull('waiting_on_person_id')->get();
            if ($reviewed && array_key_exists('top_task_ids', $chosen)) {
                $ids = is_array($chosen['top_task_ids']) ? array_map('intval', $chosen['top_task_ids']) : [-1];
                if (count($ids) !== count(array_unique($ids)) || count($ids) > 3 || count($ids) !== $tasks->whereIn('id', $ids)->count()) {
                    throw ValidationException::withMessages(['top_task_ids' => 'Choose up to three different, available open tasks that are with you.']);
                }
            } else {
                foreach ($data['task_refs'] as $reference) {
                    $matches = $tasks->filter(fn ($task) => Str::lower(Str::squish($task->title)) === Str::lower(Str::squish($reference)));
                    if ($matches->count() !== 1) {
                        throw ValidationException::withMessages(['task_refs' => 'Choose a unique open task for “'.$reference.'”. The entire Top 3 stays unchanged until all names match.']);
                    }
                    $ids[] = $matches->first()->id;
                }
                if (count($ids) !== count(array_unique($ids))) {
                    throw ValidationException::withMessages(['task_refs' => 'Choose different tasks for the Top 3.']);
                }
            }
        }

        return [$plan, $storageDate, $ids];
    }

    public function execute(User $user, Capture $capture, array $data, array $chosen, bool $reviewed): array
    {
        [$plan, $storageDate, $ids] = $this->prepare($user, $capture, $data, $chosen, $reviewed);
        $plan ??= DailyPlan::create(['user_id' => $user->id, 'plan_date' => $storageDate, 'top_task_ids' => []])->fresh();
        $before = $plan->getRawOriginal();
        $field = $data['type'] === 'set_top3' ? 'top_task_ids' : 'tomorrow_focus';
        $plan->update([$field => $field === 'top_task_ids' ? $ids : $data['body'], 'revision' => $plan->revision + 1]);

        return [$plan->fresh(), 'daily_plan', $before];
    }

    public function undo(User $user, ActionLog $log): void
    {
        $plan = DailyPlan::forUser($user)->lockForUpdate()->find($log->target_id);
        if (! $plan || $plan->getRawOriginal() != $log->after_snapshot) {
            throw ValidationException::withMessages(['undo' => 'This plan changed after capture. Edit it directly to preserve the newer plan.']);
        }
        $field = $log->action_type === 'set_top3' ? 'top_task_ids' : 'tomorrow_focus';
        $value = $log->before_snapshot[$field];
        if ($field === 'top_task_ids') {
            $value = is_string($value) ? json_decode($value, true, flags: JSON_THROW_ON_ERROR) : $value;
        }
        // Keep even a newly-created empty plan so an old revision-zero form cannot overwrite newer work.
        $plan->update([$field => $value, 'revision' => $plan->revision + 1]);
    }
}
