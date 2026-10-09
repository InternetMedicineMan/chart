<?php

namespace App\Services;

use App\Enums\ProjectLifecycle;
use App\Enums\WorkHolder;
use App\Models\Person;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WaitTracking
{
    public function save(User $user, string $type, int $id, array $data, ?CarbonInterface $occurredAt = null): void
    {
        DB::transaction(function () use ($user, $type, $id, $data, $occurredAt) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $class = $type === 'task' ? Task::class : Project::class;
            $record = $class::forUser($user)->lockForUpdate()->findOrFail($id);
            if ($record->wait_revision !== (int) $data['revision']) {
                throw ValidationException::withMessages(['revision' => 'This hand-off changed in another tab or device. Reload before saving.']);
            }
            if (! $data['waiting']) {
                $this->clear($record, $occurredAt);

                return;
            }
            if (($record instanceof Task && $record->completed_at) || ($record instanceof Project && in_array($record->lifecycle, [ProjectLifecycle::Done, ProjectLifecycle::Dropped], true))) {
                throw ValidationException::withMessages(['waiting' => 'Reopen this work before adding a wait.']);
            }
            $person = $this->person($user, $data);
            $personField = $record instanceof Task ? 'waiting_on_person_id' : 'holder_person_id';
            $sinceField = $record instanceof Task ? 'waiting_since' : 'holder_since';
            $samePerson = (int) $record->$personField === $person->id;
            $attributes = [
                $personField => $person->id,
                $sinceField => $samePerson && $record->$sinceField ? $record->$sinceField : ($occurredAt ?? now()),
                'wait_expected_by' => $data['expected_by'] ?? null,
            ];
            if ($record instanceof Project) {
                $attributes['holder'] = WorkHolder::Other;
            }
            $record->fill($attributes);
            if ($record->isDirty()) {
                $record->wait_revision++;
                $record->save();
            }
        });
    }

    private function person(User $user, array $data): Person
    {
        if (! empty($data['person_id'])) {
            return Person::forUser($user)->findOrFail($data['person_id']);
        }
        $name = Str::squish($data['new_person_name']);
        $matches = Person::forUser($user)->get()->filter(fn (Person $person) => Str::lower(Str::squish($person->name)) === Str::lower($name));
        if ($matches->count() > 1) {
            throw ValidationException::withMessages(['new_person_name' => 'More than one person has this name. Choose the existing person from the list.']);
        }

        return $matches->first() ?? Person::create(['user_id' => $user->id, 'name' => $name, 'relationship' => 'other']);
    }

    /** Call while holding the work record lock. Completion closes an open wait. */
    public function clear(Task|Project $record, ?CarbonInterface $occurredAt = null): void
    {
        $attributes = $record instanceof Task
            ? ['waiting_on_person_id' => null, 'waiting_since' => null]
            : ['holder' => WorkHolder::Me, 'holder_person_id' => null, 'holder_since' => $record->holder === WorkHolder::Other ? ($occurredAt ?? now()) : $record->holder_since];
        $record->fill($attributes + ['wait_expected_by' => null]);
        if ($record->isDirty()) {
            $record->wait_revision++;
            $record->save();
        }
    }

    public function activeProjects(User $user): Builder
    {
        return Project::forUser($user)->where('lifecycle', 'active')
            ->whereHas('domain', fn (Builder $query) => $query->forUser($user)->where('parked', false)->whereNull('archived_at'));
    }

    public function decorate(Collection $records, User $user): Collection
    {
        if ($records->isEmpty()) {
            return $records;
        }
        $relation = $records->first() instanceof Task ? 'waitingPerson' : 'holderPerson';
        $records->load([$relation => fn ($query) => $query->forUser($user)]);
        $dates = app(LocalDate::class);
        $timezone = $dates->timezone($user);
        $today = $dates->date(now(), $timezone);
        foreach ($records as $record) {
            $personId = $record instanceof Task ? $record->waiting_on_person_id : $record->holder_person_id;
            $since = $record instanceof Task ? $record->waiting_since : $record->holder_since;
            $active = $record instanceof Task ? ! $record->completed_at && $personId : $record->holder === WorkHolder::Other;
            $expected = $record->wait_expected_by?->toDateString();
            $record->setAttribute('wait', $active ? [
                'person_id' => $personId, 'person_name' => $record->getRelation($relation)?->name ?? 'Person unavailable',
                'since' => $since, 'days' => $since ? max(0, $dates->daysSince($since, $timezone)) : null,
                'expected_by' => $expected, 'overdue' => $expected !== null && $expected < $today,
            ] : null);
            $record->unsetRelation($relation);
        }

        return $records;
    }

    public function briefing(User $user): array
    {
        $tasks = app(DailyPlanning::class)->activeTasks($user)->whereNull('completed_at')->whereNotNull('waiting_on_person_id');
        $projects = $this->activeProjects($user)->where('holder', 'other');
        $total = (clone $tasks)->count() + (clone $projects)->count();
        $taskRows = $this->decorate($tasks->orderByRaw('wait_expected_by IS NULL')->orderBy('wait_expected_by')->orderBy('waiting_since')->orderBy('id')->limit(5)->get(), $user);
        $projectRows = $this->decorate($projects->orderByRaw('wait_expected_by IS NULL')->orderBy('wait_expected_by')->orderBy('holder_since')->orderBy('id')->limit(5)->get(), $user);
        $items = $taskRows->map(fn ($task) => ['type' => 'task', 'id' => $task->id, 'title' => $task->title, 'wait' => $task->wait, 'record' => $task]);
        $items = $items->concat($projectRows->map(fn ($project) => ['type' => 'project', 'id' => $project->id, 'title' => $project->name, 'wait' => $project->wait, 'record' => $project]));

        return ['total' => $total, 'items' => $items->sortBy(fn ($item) => [$item['wait']['expected_by'] ?? '9999-12-31', $item['wait']['since']?->timestamp ?? 0, $item['type'], $item['id']])->take(5)->values()];
    }
}
