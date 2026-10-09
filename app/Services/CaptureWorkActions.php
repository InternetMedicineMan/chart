<?php

namespace App\Services;

use App\Enums\WorkHolder;
use App\Models\ActionLog;
use App\Models\ActivityLog;
use App\Models\Capture;
use App\Models\Domain;
use App\Models\Person;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CaptureWorkActions
{
    public const TYPES = ['log_activity', 'set_waiting', 'clear_waiting', 'complete_task'];

    public function resolve(User $user, array $data, array $chosen = [], bool $lock = false): array
    {
        $domains = Domain::forUser($user)->where('parked', false)->whereNull('archived_at')->get();
        $domainId = $this->exact($data['domain_ref'] ?? null, $domains, 'domain', $chosen['domain_id'] ?? null, false);
        $projects = Project::forUser($user)->where('lifecycle', 'active')->whereIn('domain_id', $domains->pluck('id'))->when($domainId, fn ($q) => $q->where('domain_id', $domainId))->get();
        $projectId = $this->exact($data['project_ref'] ?? null, $projects, 'project', $chosen['project_id'] ?? null, false);
        $isTask = filled($data['task_ref'] ?? null) || ! empty($chosen['task_id']);
        if (in_array($data['type'], ['set_waiting', 'clear_waiting', 'complete_task'], true) && $isTask) {
            $tasks = app(DailyPlanning::class)->activeTasks($user)->whereNull('completed_at')->when($projectId, fn ($q) => $q->where('project_id', $projectId))->when($domainId, fn ($q) => $q->where('domain_id', $domainId))->get(['id', 'title as name']);
            $id = $this->exact($data['task_ref'] ?? null, $tasks, 'task', $chosen['task_id'] ?? null);
            $subject = Task::forUser($user)->when($lock, fn ($q) => $q->lockForUpdate())->findOrFail($id);
        } elseif ($projectId && $data['type'] !== 'complete_task') {
            $subject = Project::forUser($user)->when($lock, fn ($q) => $q->lockForUpdate())->findOrFail($projectId);
        } elseif ($data['type'] === 'log_activity' && $domainId) {
            $subject = Domain::forUser($user)->when($lock, fn ($q) => $q->lockForUpdate())->findOrFail($domainId);
        } else {
            throw ValidationException::withMessages(['subject' => in_array($data['type'], ['set_waiting', 'clear_waiting'], true) ? 'Choose the existing task or project for this hand-off.' : ($data['type'] === 'complete_task' ? 'Choose the existing task to complete.' : 'Choose the project or domain for this activity.')]);
        }
        $person = null;
        if ($data['type'] === 'set_waiting' || ($data['type'] === 'clear_waiting' && filled($data['person_ref'] ?? null))) {
            $people = Person::forUser($user)->get();
            $person = $people->find($this->exact($data['person_ref'] ?? null, $people, 'person', $chosen['person_id'] ?? null));
        }

        if ($data['type'] === 'clear_waiting') {
            $this->assertWaiting($subject, $person);
        }

        return [$subject, $person];
    }

    private function exact(?string $reference, Collection $records, string $kind, ?int $chosen = null, bool $required = true): ?int
    {
        if ($chosen) {
            if (! $records->contains('id', $chosen)) {
                throw ValidationException::withMessages([$kind => 'Choose an available '.$kind.' from your active work.']);
            }

            return $chosen;
        }
        if (! filled($reference) && ! $required) {
            return null;
        }
        $matches = $records->filter(fn ($record) => Str::lower(Str::squish($record->name)) === Str::lower(Str::squish($reference ?? '')));
        if ($matches->count() !== 1) {
            throw ValidationException::withMessages([$kind => "Choose a unique {$kind} for “{$reference}”. Automatic changes require an exact name match."]);
        }

        return $matches->first()->id;
    }

    public function execute(User $user, Capture $capture, array $data, array $chosen, bool $reviewed): array
    {
        if (! $reviewed && $data['confidence'] < .8) {
            throw ValidationException::withMessages(['action' => 'Review this action before changing your work.']);
        }
        [$subject, $person] = $this->resolve($user, $data, $chosen, true);
        if ($data['type'] === 'log_activity') {
            $target = app(ActivityTracking::class)->capture($user, $subject, $data['body'], $data['minutes'] ?? null, $this->activityTime($capture, $data));

            return [$target, 'activity', null];
        }
        if ($data['type'] === 'complete_task') {
            $this->assertCompletion($subject, $capture, $chosen, $reviewed);
            $before = $subject->getRawOriginal();
            app(TaskCompletion::class)->complete($user, $subject, $capture->client_captured_at);

            return [$subject->fresh(), 'task', $before];
        }
        $this->assertCurrentWait($subject, $capture, $chosen, $reviewed);
        $before = $subject->getRawOriginal();
        $type = $subject instanceof Task ? 'task' : 'project';
        app(WaitTracking::class)->save($user, $type, $subject->id, ['waiting' => $data['type'] === 'set_waiting', 'revision' => $subject->wait_revision, 'person_id' => $person?->id, 'expected_by' => $data['expected_by'] ?? null], $capture->client_captured_at);

        return [$subject->fresh(), $type, $before];
    }

    public function activityTime(Capture $capture, array $data): CarbonImmutable
    {
        $occurred = $capture->client_captured_at;
        if (! empty($data['activity_date']) || ! empty($data['activity_time'])) {
            $local = $occurred->setTimezone($capture->timezone);
            $input = ($data['activity_date'] ?? $local->toDateString()).'T'.($data['activity_time'] ?? $local->format('H:i'));
            $occurred = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $input, $capture->timezone);
            if ($occurred->format('Y-m-d\TH:i') !== $input) {
                throw ValidationException::withMessages(['activity_date' => 'This activity time does not exist in the capture timezone.']);
            }
        }
        if ($occurred->isFuture()) {
            throw ValidationException::withMessages(['activity_date' => 'Activity must describe work that already happened.']);
        }

        return $occurred->utc();
    }

    private function assertWaiting(Task|Project $subject, ?Person $person): void
    {
        $waiting = $subject instanceof Task ? $subject->waiting_on_person_id !== null : $subject->holder === WorkHolder::Other;
        $currentPerson = $subject instanceof Task ? $subject->waiting_on_person_id : $subject->holder_person_id;
        if (! $waiting || ($person && $person->id !== $currentPerson)) {
            throw ValidationException::withMessages(['waiting' => 'This work is not waiting on the stated person, or its wait has already ended. Review the current hand-off.']);
        }
    }

    public function assertCurrentWait(Task|Project $subject, Capture $capture, array $chosen = [], bool $reviewed = false): void
    {
        if ($capture->client_captured_at->isFuture()) {
            throw ValidationException::withMessages(['waiting' => 'The recording time is in the future. Review its time before changing a wait.']);
        }
        if ($reviewed) {
            if (! isset($chosen['work_revision']) || $subject->wait_revision !== (int) $chosen['work_revision']) {
                throw ValidationException::withMessages(['revision' => 'This hand-off changed while you were reviewing. Reload the capture and choose it again.']);
            }
        } elseif ($subject->updated_at->gte($capture->client_captured_at->startOfSecond())) {
            throw ValidationException::withMessages(['revision' => 'This work changed at or after the recording. Review the current hand-off before applying it.']);
        }
    }

    public function assertCompletion(Task $task, Capture $capture, array $chosen = [], bool $reviewed = false): void
    {
        if ($capture->client_captured_at->isFuture()) {
            throw ValidationException::withMessages(['completed_at' => 'The recording time is in the future. Review its time before completing work.']);
        }
        if ($reviewed) {
            if (! isset($chosen['task_revision']) || (int) $chosen['task_revision'] !== $task->revision) {
                throw ValidationException::withMessages(['revision' => 'This task changed while you were reviewing. Reload and choose it again.']);
            }
        } elseif ($task->updated_at->gte($capture->client_captured_at->startOfSecond())
            || ($task->recurrence_rule && $task->due_date->toDateString() > $capture->client_captured_at->setTimezone($task->recurrence_timezone)->toDateString())) {
            throw ValidationException::withMessages(['revision' => 'This task changed after recording or is a future repeat. Review the exact occurrence before completing it.']);
        }
    }

    public function undo(User $user, ActionLog $log): void
    {
        if ($log->action_type === 'log_activity') {
            $target = ActivityLog::withTrashed()->forUser($user)->lockForUpdate()->find($log->target_id);
        } else {
            $class = $log->target_type === 'task' ? Task::class : Project::class;
            $target = $class::withTrashed()->forUser($user)->lockForUpdate()->find($log->target_id);
        }
        if (! $target || $target->getRawOriginal() != $log->after_snapshot) {
            throw ValidationException::withMessages(['undo' => 'This record changed after capture. Keep newer work by editing it directly.']);
        }
        if ($log->action_type === 'complete_task') {
            app(TaskCompletion::class)->reopen($user, $target, true);

            return;
        }
        if ($target instanceof ActivityLog) {
            app(ActivityTracking::class)->delete($user, $target->id, $target->revision);

            return;
        }
        if (! is_array($log->before_snapshot)) {
            throw ValidationException::withMessages(['undo' => 'This hand-off has no saved prior state. Edit it directly.']);
        }
        $fields = $target instanceof Task ? ['waiting_on_person_id', 'waiting_since', 'wait_expected_by'] : ['holder', 'holder_person_id', 'holder_since', 'wait_expected_by'];
        $target->fill(array_intersect_key($log->before_snapshot, array_flip($fields)));
        $target->wait_revision++;
        $target->save();
    }
}
