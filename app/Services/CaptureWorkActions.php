<?php

namespace App\Services;

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
    public const TYPES = ['log_activity', 'set_waiting'];

    public function resolve(User $user, array $data, array $chosen = [], bool $lock = false): array
    {
        $domains = Domain::forUser($user)->where('parked', false)->whereNull('archived_at')->get();
        $domainId = $this->exact($data['domain_ref'] ?? null, $domains, 'domain', $chosen['domain_id'] ?? null, false);
        $projects = Project::forUser($user)->where('lifecycle', 'active')->whereIn('domain_id', $domains->pluck('id'))->when($domainId, fn ($q) => $q->where('domain_id', $domainId))->get();
        $projectId = $this->exact($data['project_ref'] ?? null, $projects, 'project', $chosen['project_id'] ?? null, false);
        $isTask = filled($data['task_ref'] ?? null) || ! empty($chosen['task_id']);
        if ($data['type'] === 'set_waiting' && $isTask) {
            $tasks = app(DailyPlanning::class)->activeTasks($user)->whereNull('completed_at')->when($projectId, fn ($q) => $q->where('project_id', $projectId))->when($domainId, fn ($q) => $q->where('domain_id', $domainId))->get(['id', 'title as name']);
            $id = $this->exact($data['task_ref'] ?? null, $tasks, 'task', $chosen['task_id'] ?? null);
            $subject = Task::forUser($user)->when($lock, fn ($q) => $q->lockForUpdate())->findOrFail($id);
        } elseif ($projectId) {
            $subject = Project::forUser($user)->when($lock, fn ($q) => $q->lockForUpdate())->findOrFail($projectId);
        } elseif ($data['type'] === 'log_activity' && $domainId) {
            $subject = Domain::forUser($user)->when($lock, fn ($q) => $q->lockForUpdate())->findOrFail($domainId);
        } else {
            throw ValidationException::withMessages(['subject' => $data['type'] === 'set_waiting' ? 'Choose the existing task or project to put on hold.' : 'Choose the project or domain for this activity.']);
        }
        $person = null;
        if ($data['type'] === 'set_waiting') {
            $people = Person::forUser($user)->get();
            $person = $people->find($this->exact($data['person_ref'] ?? null, $people, 'person', $chosen['person_id'] ?? null));
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
            throw ValidationException::withMessages(['action' => 'Review this activity or hand-off before changing your work.']);
        }
        [$subject, $person] = $this->resolve($user, $data, $chosen, true);
        if ($data['type'] === 'log_activity') {
            $target = app(ActivityTracking::class)->capture($user, $subject, $data['body'], $data['minutes'] ?? null, $this->activityTime($capture, $data));

            return [$target, 'activity', null];
        }
        $this->assertCurrentWait($subject, $capture, $chosen, $reviewed);
        $before = $subject->getRawOriginal();
        $type = $subject instanceof Task ? 'task' : 'project';
        app(WaitTracking::class)->save($user, $type, $subject->id, ['waiting' => true, 'revision' => $subject->wait_revision, 'person_id' => $person->id, 'expected_by' => $data['expected_by'] ?? null], $capture->client_captured_at);

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

    public function assertCurrentWait(Task|Project $subject, Capture $capture, array $chosen = [], bool $reviewed = false): void
    {
        if ($reviewed) {
            if (! isset($chosen['work_revision']) || $subject->wait_revision !== (int) $chosen['work_revision']) {
                throw ValidationException::withMessages(['revision' => 'This hand-off changed while you were reviewing. Reload the capture and choose it again.']);
            }
        } elseif ($subject->updated_at->gte($capture->client_captured_at->startOfSecond())) {
            throw ValidationException::withMessages(['revision' => 'This work changed at or after the recording. Review the current hand-off before applying it.']);
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
