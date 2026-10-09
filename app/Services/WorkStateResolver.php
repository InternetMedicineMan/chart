<?php

namespace App\Services;

use App\Enums\ProjectLifecycle;
use App\Enums\ProjectType;
use App\Enums\WorkHolder;
use App\Models\DailyPlan;
use App\Models\Domain;
use App\Models\Person;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class WorkStateResolver
{
    public const RANK = ['quiet' => 3, 'my_move' => 2, 'waiting' => 1, 'ok' => 0, 'excluded' => -1];

    /** One bulk query set per page, with no persisted state that can outlive an edit or local midnight. */
    public function snapshot(User $user): array
    {
        $dates = app(LocalDate::class);
        $timezone = $dates->timezone($user);
        $today = $dates->date(now(), $timezone);
        $domains = Domain::forUser($user)->orderBy('sort_order')->get()->keyBy('id');
        $projects = Project::forUser($user)->get()->keyBy('id');
        $topIds = DailyPlan::forUser($user)->whereDate('plan_date', $today)->first()?->top_task_ids ?: [0];
        $placeholders = implode(',', array_fill(0, count($topIds), '?'));
        $tasks = app(DailyPlanning::class)->activeTasks($user)->whereNull('completed_at');
        $buckets = (clone $tasks)->select(['domain_id', 'project_id'])
            ->selectRaw('COUNT(*) AS open_count')
            ->selectRaw('SUM(CASE WHEN waiting_on_person_id IS NOT NULL THEN 1 ELSE 0 END) AS waiting_count')
            ->selectRaw('SUM(CASE WHEN waiting_on_person_id IS NULL AND due_date < ? THEN 1 ELSE 0 END) AS overdue_count', [$today])
            ->selectRaw('SUM(CASE WHEN waiting_on_person_id IS NULL AND due_date = ? THEN 1 ELSE 0 END) AS due_today_count', [$today])
            ->selectRaw("SUM(CASE WHEN waiting_on_person_id IS NULL AND id IN ({$placeholders}) THEN 1 ELSE 0 END) AS top_count", $topIds)
            ->groupBy('domain_id', 'project_id')->get();
        $waitingTasks = (clone $tasks)->whereNotNull('waiting_on_person_id')
            ->get(['id', 'domain_id', 'project_id', 'waiting_on_person_id', 'waiting_since', 'wait_expected_by']);
        $people = Person::forUser($user)->whereIn('id', $waitingTasks->pluck('waiting_on_person_id')->merge($projects->pluck('holder_person_id'))->filter()->unique())->pluck('name', 'id');
        $waitsByProject = [];
        $waitsByDomain = [];
        foreach ($waitingTasks as $task) {
            $wait = $this->wait($task->waiting_on_person_id, $task->waiting_since, $task->wait_expected_by?->toDateString(), $people, $timezone, $today) + ['source_project_id' => $task->project_id];
            $waitsByDomain[$task->domain_id][] = $wait;
            if ($task->project_id) {
                $waitsByProject[$task->project_id][] = $wait;
            }
        }
        $projectStates = [];
        foreach ($projects as $project) {
            $domain = $domains->get($project->domain_id);
            $excluded = ! $domain || $domain->parked || $domain->archived_at || $project->lifecycle !== ProjectLifecycle::Active;
            $waits = collect($waitsByProject[$project->id] ?? []);
            if (! $excluded && $project->holder === WorkHolder::Other) {
                $wait = $this->wait($project->holder_person_id, $project->holder_since, $project->wait_expected_by?->toDateString(), $people, $timezone, $today) + ['source_project_id' => $project->id];
                $waits->push($wait);
                $waitsByDomain[$project->domain_id][] = $wait;
            }
            $counts = $this->counts($buckets->where('project_id', $project->id));
            $projectStates[$project->id] = $this->resolve($project, $counts, $waits, $timezone, $excluded);
        }
        $domainStates = [];
        foreach ($domains as $domain) {
            $state = $this->resolve($domain, $this->counts($buckets->where('domain_id', $domain->id)), collect($waitsByDomain[$domain->id] ?? []), $timezone, $domain->parked || $domain->archived_at !== null);
            if ($state['state'] !== 'excluded') {
                foreach ($projects->where('domain_id', $domain->id) as $project) {
                    $child = $projectStates[$project->id];
                    if (self::RANK[$child['state']] > self::RANK[$state['state']] || ($child['state'] === $state['state'] && $child['score'] > $state['score'])) {
                        $state = array_replace($state, array_intersect_key($child, array_flip(['state', 'since', 'holder', 'score'])));
                        $state['reason'] = $project->name.': '.$child['reason'];
                        $state['source_project_id'] = $project->id;
                    }
                }
            }
            $domainStates[$domain->id] = $state;
        }

        return ['projects' => collect($projectStates), 'domains' => collect($domainStates), 'projectRecords' => $projects, 'domainRecords' => $domains];
    }

    private function counts(Collection $buckets): array
    {
        return collect(['open_count', 'waiting_count', 'overdue_count', 'due_today_count', 'top_count'])
            ->mapWithKeys(fn ($key) => [$key => (int) $buckets->sum($key)])->all();
    }

    private function wait(?int $personId, $since, ?string $expected, Collection $people, string $timezone, string $today): array
    {
        return ['person_id' => $personId, 'person_name' => $people->get($personId, 'Person unavailable'), 'since' => $since?->toISOString(),
            'days' => $since ? max(0, app(LocalDate::class)->daysSince($since, $timezone)) : null,
            'expected_by' => $expected, 'overdue' => $expected !== null && $expected < $today,
            'overdue_days' => $expected ? max(0, (int) CarbonImmutable::parse($expected, 'UTC')->diffInDays(CarbonImmutable::parse($today, 'UTC'))) : 0];
    }

    private function resolve(Domain|Project $record, array $counts, Collection $waits, string $timezone, bool $excluded): array
    {
        $dates = app(LocalDate::class);
        $last = $record->last_touched_at;
        $age = max(0, $dates->daysSince($last ?? $record->created_at, $timezone));
        $oldestWait = $waits->sortBy(fn ($wait) => [$wait['since'] ?? '9999', $wait['person_id'] ?? 0])->first();
        $overdueWait = $waits->where('overdue', true)->sortBy('expected_by')->first();
        $state = ['state' => 'ok', 'reason' => 'No immediate move', 'since' => null, 'holder' => null,
            'score' => 0, 'days_since_touch' => $last ? $age : null, 'cadence_days' => $record->cadence_days,
            'recency' => ! $last ? 'No activity yet' : ($age === 0 ? 'Active today' : "Active {$age} days ago"),
            'counts' => $counts, 'oldest_wait' => $oldestWait, 'source_project_id' => null];
        if ($excluded) {
            return array_replace($state, ['state' => 'excluded', 'reason' => 'Outside active work']);
        }
        if ($record->quiet_enabled && $record->cadence_days && $age > $record->cadence_days) {
            return array_replace($state, ['state' => 'quiet', 'reason' => "{$age} days ".($last ? 'since activity' : 'without activity')." · cadence {$record->cadence_days} days",
                'since' => ($last ?? $record->created_at)->toISOString(), 'holder' => $oldestWait, 'score' => 40 + min(100, (int) floor(40 * ($age - $record->cadence_days) / $record->cadence_days))]);
        }
        if ($overdueWait) {
            return array_replace($state, ['state' => 'quiet', 'reason' => 'Wait overdue: '.$overdueWait['person_name'].' · expected '.$overdueWait['expected_by'],
                'since' => $overdueWait['since'], 'holder' => $overdueWait, 'source_project_id' => $overdueWait['source_project_id'], 'score' => 60 + min(100, $overdueWait['overdue_days'])]);
        }
        if ($counts['overdue_count'] + $counts['due_today_count'] + $counts['top_count'] > 0) {
            return array_replace($state, ['state' => 'my_move', 'reason' => $counts['overdue_count'] ? 'Open tasks are overdue' : ($counts['due_today_count'] ? 'Open tasks are due today' : 'A task is in today’s Top 3')]);
        }
        if ($oldestWait) {
            return array_replace($state, ['state' => 'waiting', 'reason' => 'Waiting on '.$oldestWait['person_name'], 'since' => $oldestWait['since'], 'holder' => $oldestWait]);
        }
        if ($record instanceof Project && $record->type === ProjectType::TargetDate && $counts['open_count']) {
            return array_replace($state, ['state' => 'my_move', 'reason' => 'Open work toward a finite outcome']);
        }

        return $state;
    }

    public function quiet(array $snapshot): array
    {
        $items = collect();
        foreach (['domain', 'project'] as $type) {
            foreach ($snapshot[$type.'s'] as $id => $state) {
                if ($state['state'] !== 'quiet') {
                    continue;
                }
                // A roll-up is still visible on Bench; the briefing shows the originating project once.
                if ($type === 'domain' && $state['source_project_id']) {
                    continue;
                }
                $record = $snapshot[$type.'Records']->get($id);
                $items->push(['type' => $type, 'id' => $id, 'name' => $record->name, 'work_state' => $state]);
            }
        }
        $items = $items->sortBy(fn ($item) => [-$item['work_state']['score'], $item['work_state']['since'] ?? '', $item['type'], $item['id']])->values();

        return ['total' => $items->count(), 'items' => $items->take(5)->values()];
    }
}
