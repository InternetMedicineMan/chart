<?php

namespace App\Services;

use App\Enums\WorkHolder;
use App\Models\AppSetting;
use App\Models\Capture;
use App\Models\Note;
use App\Models\Observation;
use App\Models\Person;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class BriefingObservations
{
    /** Nightly creation; reads reconcile existing signals so completed or parked work disappears immediately. */
    public function refresh(User $user, bool $create = false, bool $scheduled = false): void
    {
        DB::transaction(function () use ($user, $create, $scheduled) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $dates = app(LocalDate::class);
            $local = CarbonImmutable::now($dates->timezone($user));
            $stamp = AppSetting::forUser($user)->where('key', 'observations_last_run')->first();
            if ($scheduled && ($local->hour < 2 || $stamp?->value === $local->toDateString())) {
                return;
            }
            $candidates = collect($this->candidates($user))->keyBy('identity');
            $existing = Observation::forUser($user)->where('expires_at', '>', now())->get();
            $week = $local->format('o-\WW');
            $changes = [];
            $resolvedIds = [];
            $existingByKey = $existing->keyBy('dedup_key');
            foreach ($existing as $row) {
                $identity = "{$row->rule_type}:{$row->subject_type}:{$row->subject_id}";
                $candidate = $candidates->get($identity);
                $key = $candidate ? $identity.':'.($candidate['daily'] ? $local->toDateString() : $week) : null;
                if (! $candidate || ($create && $row->dedup_key !== $key)) {
                    if (! $row->resolved_at) {
                        $resolvedIds[] = $row->id;
                    }
                } elseif (! $create && ! $row->resolved_at) {
                    $row->fill($this->attributes($candidate));
                    if ($row->isDirty()) {
                        $changes[] = $this->row($row);
                    }
                }
            }
            if ($create) {
                foreach ($candidates as $identity => $candidate) {
                    $key = $identity.':'.($candidate['daily'] ? $local->toDateString() : $week);
                    $snooze = $existing->filter(fn ($row) => "{$row->rule_type}:{$row->subject_type}:{$row->subject_id}" === $identity && $row->snoozed_until?->isFuture())->max('snoozed_until');
                    $row = $existingByKey->get($key) ?? new Observation([
                        'user_id' => $user->id, 'dedup_key' => $key, 'observed_on' => $local->toDateString(), 'expires_at' => now()->addDays(60),
                        'snoozed_until' => $snooze, 'dismissed_at' => null, 'acted_on_at' => null,
                    ]);
                    $row->fill($this->attributes($candidate) + ['resolved_at' => null]);
                    if ($row->isDirty()) {
                        $changes[] = $this->row($row);
                    }
                }
            }
            if ($changes) {
                Observation::query()->upsert($changes, ['user_id', 'dedup_key'], ['title', 'body', 'suggested_action', 'data', 'score', 'urgency', 'roll_up_count', 'resolved_at', 'updated_at']);
            }
            Observation::forUser($user)->whereNull('resolved_at')->where(fn ($q) => $q->whereIn('id', $resolvedIds)->orWhere('expires_at', '<=', now()))->update(['resolved_at' => now()]);
            if (! $create) {
                return;
            }
            AppSetting::forUser($user)->updateOrCreate(['key' => 'observations_last_run'], ['user_id' => $user->id, 'value' => $local->toDateString()]);
        });
    }

    private function row(Observation $row): array
    {
        $attributes = $row->getAttributes();
        unset($attributes['id']);
        $attributes['created_at'] ??= now()->toDateTimeString();
        $attributes['updated_at'] = now()->toDateTimeString();

        return $attributes;
    }

    private function attributes(array $candidate): array
    {
        unset($candidate['identity'], $candidate['daily']);

        return $candidate + ['urgency' => $candidate['score'] >= 80 ? 'high' : ($candidate['score'] >= 30 ? 'normal' : 'low')];
    }

    public function candidates(User $user): array
    {
        $dates = app(LocalDate::class);
        $timezone = $dates->timezone($user);
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $snapshot = app(WorkStateResolver::class)->snapshot($user);
        $items = [];
        $add = function (string $rule, string $type, int $id, string $title, string $body, int $score, string $url, bool $daily = false, ?int $count = null) use (&$items) {
            $items[] = ['identity' => "{$rule}:{$type}:{$id}", 'rule_type' => $rule, 'subject_type' => $type, 'subject_id' => $id, 'title' => $title, 'body' => $body, 'score' => $score, 'data' => ['url' => $url], 'suggested_action' => 'Open', 'daily' => $daily, 'roll_up_count' => $count];
        };
        foreach (['domain', 'project'] as $type) {
            foreach ($snapshot[$type.'Records'] as $record) {
                $state = $snapshot[$type.'s']->get($record->id);
                if ($state['state'] === 'excluded' || ! $record->quiet_enabled || ($type === 'domain' && $record->is_inbox)) {
                    continue;
                }
                $age = max(0, $dates->daysSince($record->last_touched_at ?? $record->created_at, $timezone));
                $url = $type === 'project' ? route('projects.show', $record->id, false) : route('bench', ['domain' => $record->id], false);
                if ($record->cadence_days && $age > $record->cadence_days) {
                    $score = 40 + min(100, (int) floor(40 * ($age - $record->cadence_days) / $record->cadence_days));
                    $add($type.'_quiet', $type, $record->id, $record->name.' has gone quiet', "{$age} days without activity · cadence {$record->cadence_days} days", $score, $url);
                } elseif ($type === 'project' && $age >= 14) {
                    $add('project_stalled', $type, $record->id, $record->name.' has no recent activity', "{$age} days without recorded activity", 40, $url);
                }
            }
        }
        $tasks = app(DailyPlanning::class)->activeTasks($user)->whereNull('completed_at')->with(['waitingPerson' => fn ($q) => $q->forUser($user)])->get();
        foreach ($tasks as $task) {
            $url = route('tasks.show', $task->id, false);
            if ($task->waiting_on_person_id) {
                $this->wait($add, 'task', $task->id, $task->title, $task->waitingPerson?->name ?? 'Person unavailable', $task->waiting_since, $task->wait_expected_by, $timezone, $url);
            } elseif ($task->due_date && $task->due_date->toDateString() <= $today->addDays(3)->toDateString()) {
                $due = $task->due_date->toDateString();
                $score = 55 + ($due < $today->toDateString() ? 100 : ($due === $today->toDateString() ? 50 : 0));
                $add('task_due', 'task', $task->id, $task->title, 'Due '.$due, $score, $url, true);
            }
        }
        $people = Person::forUser($user)->whereIn('id', $snapshot['projectRecords']->pluck('holder_person_id')->filter()->unique())->pluck('name', 'id');
        foreach ($snapshot['projectRecords'] as $project) {
            if ($snapshot['projects']->get($project->id)['state'] !== 'excluded' && $project->holder === WorkHolder::Other) {
                $this->wait($add, 'project', $project->id, $project->name, $people->get($project->holder_person_id, 'Person unavailable'), $project->holder_since, $project->wait_expected_by, $timezone, route('projects.show', $project->id, false));
            }
        }
        $cutoff = $today->subDays(30)->endOfDay()->utc();
        $ideas = Note::forUser($user)->where('kind', 'thought')->where(fn ($q) => $q->where('reviewed_at', '<=', $cutoff)->orWhere(fn ($q) => $q->whereNull('reviewed_at')->where('created_at', '<=', $cutoff)))->count();
        if ($ideas >= 3) {
            $add('ideas_aging', 'ideas', $user->id, "{$ideas} ideas to revisit", 'Not reviewed for at least 30 days', 25, route('ideas', [], false), false, $ideas);
        }
        $backlog = Capture::forUser($user)->whereIn('status', ['needs_triage', 'partially_executed', 'failed'])->where('created_at', '<', $today->subDays(7)->utc())->count();
        if ($backlog >= 3) {
            $add('inbox_backlog', 'captures', $user->id, "{$backlog} captures need a decision", 'Waiting for review for more than seven days', 20, route('intake', ['review' => 1], false), false, $backlog);
        }

        $load = app(CalendarBriefing::class)->tomorrow($user);
        if ($load) {
            $hours = round($load['minutes'] / 60, 1).($load['minutes'] === 60 ? ' hour' : ' hours');
            $free = round($load['focus_minutes'] / 60, 1).($load['focus_minutes'] === 60 ? ' hour' : ' hours');
            $allDay = $load['all_day_count'] ? " · {$load['all_day_count']} busy all-day events" : '';
            $add('tomorrow_load', 'calendar', $user->id, "Tomorrow: {$hours} booked", "{$free} unbooked, 8 a.m.–5 p.m.{$allDay}. Overlapping events count once; busy all-day events block the window.", 20, route('calendar.index', ['date' => $load['date']], false), true);
        }

        return $items;
    }

    private function wait(callable $add, string $type, int $id, string $name, string $person, $since, $expected, string $timezone, string $url): void
    {
        $dates = app(LocalDate::class);
        $days = $since ? max(0, $dates->daysSince($since, $timezone)) : 0;
        $overdue = $expected && $expected->toDateString() < $dates->date(now(), $timezone);
        if (! $overdue && $days < 7) {
            return;
        }
        $score = 30 + 20 * max(0, (int) floor(($days - 7) / 7));
        if ($type === 'project' && $days >= 10) {
            $score = max($score, 45 + 15 * (int) floor(($days - 10) / 7));
        }
        if ($overdue) {
            $score = max($score, 60 + min(100, (int) CarbonImmutable::parse($expected->toDateString(), 'UTC')->diffInDays(CarbonImmutable::parse($dates->date(now(), $timezone), 'UTC'))));
        }
        $add('wait_aging', $type, $id, $name, 'Waiting on '.$person.($since ? " · {$days} days" : '').($expected ? ' · expected '.$expected->toDateString() : ''), $score, $url);
    }

    public function briefing(User $user): array
    {
        $this->refresh($user);
        $query = Observation::forUser($user)->visible();

        return ['total' => (clone $query)->count(), 'items' => $query->orderByDesc('score')->orderBy('id')->limit(5)->get()];
    }
}
