<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AppSetting;
use App\Models\Capture;
use App\Models\CaptureToken;
use App\Models\Note;
use App\Models\Project;
use App\Models\Task;
use App\Services\CaptureService;
use App\Services\DailyPlanning;
use App\Services\LocalDate;
use App\Services\WaitTracking;
use App\Services\WorkOptions;
use App\Services\WorkStateResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WorkController extends Controller
{
    public function dashboard(Request $request, WorkOptions $options, LocalDate $dates, DailyPlanning $planning, WaitTracking $waits, WorkStateResolver $states): Response
    {
        $user = $request->user();
        $snapshot = $states->snapshot($user);
        $plan = $planning->today($user);
        $due = $planning->activeTasks($user)->whereNotIn('id', $plan['tasks']->pluck('id'))->whereNull('completed_at')->whereNull('waiting_on_person_id')->whereDate('due_date', '<=', $dates->today($user));

        return Inertia::render('Dashboard', [
            'options' => $options->forUser($user),
            'dailyPlan' => $plan,
            'quiet' => $states->quiet($snapshot),
            'waiting' => $waits->briefing($user),
            'dueCount' => (clone $due)->count(),
            'dueTasks' => $due->with(['project', 'domain'])->orderBy('due_date')->orderBy('priority')->orderBy('id')->limit(7)->get(),
            'inboxCount' => Task::forUser($user)->whereNull('completed_at')->whereHas('domain', fn (Builder $query) => $query->forUser($user)->where('is_inbox', true))->count(),
            'ideaCount' => Note::forUser($user)->where('kind', 'thought')->count(),
            'projectCount' => Project::forUser($user)->where('lifecycle', 'active')->count(),
            'oldCaptureCount' => Capture::forUser($user)->whereIn('status', ['needs_triage', 'partially_executed', 'failed'])->where('created_at', '<=', now()->subHours(48))->count(),
        ]);
    }

    public function bench(Request $request, WorkOptions $options, WaitTracking $waits, WorkStateResolver $states): Response
    {
        $filters = $request->validate([
            'sphere' => ['nullable', Rule::in(['personal', 'work'])],
            'state' => ['nullable', Rule::in(['quiet', 'my_move', 'waiting', 'ok'])],
            'domain' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['open', 'waiting', 'completed', 'trash'])],
            'project_status' => ['nullable', Rule::in(['current', 'waiting', 'trash'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $snapshot = $states->snapshot($request->user());
        $tasks = Task::forUser($request->user())->with(['domain', 'project']);
        $projects = Project::forUser($request->user())->with('domain')
            ->withSum(['milestones as milestone_weight' => fn ($q) => $q->forUser($request->user())], 'weight')
            ->withSum(['milestones as completed_milestone_weight' => fn ($q) => $q->forUser($request->user())->whereNotNull('completed_at')], 'weight')
            ->withCount(['tasks as open_tasks_count' => fn (Builder $query) => $query->forUser($request->user())->whereNull('completed_at')]);
        if (($filters['project_status'] ?? 'current') === 'trash') {
            $projects->onlyTrashed();
        } else {
            $projects->where('lifecycle', '!=', 'someday');
            if (($filters['project_status'] ?? null) === 'waiting') {
                $projects->where('holder', 'other')->where('lifecycle', 'active')
                    ->whereHas('domain', fn ($query) => $query->where('parked', false)->whereNull('archived_at'));
            }
        }
        foreach ([$tasks, $projects] as $query) {
            $query->when($filters['domain'] ?? null, fn (Builder $q, $id) => $q->where('domain_id', $id))
                ->when($filters['sphere'] ?? null, fn (Builder $q, $sphere) => $q->whereHas('domain', fn (Builder $domain) => $domain->forUser($request->user())->where('sphere', $sphere)));
        }
        $tasks->when($filters['q'] ?? null, fn (Builder $q, $text) => $q->where('title', 'like', '%'.$text.'%'));
        $projects->when($filters['q'] ?? null, fn (Builder $q, $text) => $q->where('name', 'like', '%'.$text.'%'));
        match ($filters['status'] ?? 'open') {
            'waiting' => $tasks->whereNull('completed_at')->whereNotNull('waiting_on_person_id')->whereIn('id', app(DailyPlanning::class)->activeTasks($request->user())->select('tasks.id')),
            'completed' => $tasks->whereNotNull('completed_at'),
            'trash' => $tasks->onlyTrashed(),
            default => $tasks->whereNull('completed_at'),
        };

        if ($filters['state'] ?? null) {
            $projectIds = $snapshot['projects']->filter(fn ($state) => $state['state'] === $filters['state'])->keys();
            $domainIds = $snapshot['domains']->filter(fn ($state) => $state['state'] === $filters['state'])->keys();
            $projects->whereIn('id', $projectIds);
            $tasks->where(fn ($query) => $query->whereIn('project_id', $projectIds)->orWhere(fn ($query) => $query->whereNull('project_id')->whereIn('domain_id', $domainIds)));
        }
        foreach (['quiet', 'my_move', 'waiting', 'ok'] as $state) {
            $ids = $snapshot['projects']->filter(fn ($row) => $row['state'] === $state)->keys()->all();
            if ($ids) {
                $projects->orderByRaw('CASE WHEN id IN ('.implode(',', array_fill(0, count($ids), '?')).') THEN 0 ELSE 1 END', $ids);
            }
        }
        $projectPage = $projects->orderByRaw('target_date IS NULL')->orderBy('target_date')->orderBy('name')->paginate(12, ['*'], 'projects_page')->withQueryString();
        $projectPage->getCollection()->each(fn ($project) => $project->setAttribute('work_state', $snapshot['projects']->get($project->id)));
        $workOptions = $options->forUser($request->user());
        $workOptions['domains']->each(fn ($domain) => $domain->setAttribute('work_state', $snapshot['domains']->get($domain->id)));

        $taskPage = $tasks->orderByRaw('due_date IS NULL')->orderBy('due_date')->orderBy('priority')->orderByDesc('id')->paginate(20)->withQueryString();
        $waits->decorate($projectPage->getCollection(), $request->user());
        $waits->decorate($taskPage->getCollection(), $request->user());

        return Inertia::render('Work/Bench', [
            'options' => $workOptions, 'filters' => $filters,
            'projects' => $projectPage,
            'tasks' => $taskPage,
        ]);
    }

    public function project(Request $request, int $project, WorkOptions $options, WaitTracking $waits, WorkStateResolver $states): Response
    {
        $record = Project::forUser($request->user())->with(['domain', 'milestones' => fn ($q) => $q->forUser($request->user())->orderBy('sort_order')->orderBy('id')])->withCount([
            'tasks as open_tasks_count' => fn (Builder $q) => $q->forUser($request->user())->whereNull('completed_at'),
            'tasks as completed_tasks_count' => fn (Builder $q) => $q->forUser($request->user())->whereNotNull('completed_at'),
        ])->findOrFail($project);
        $record->setAttribute('work_state', $states->snapshot($request->user())['projects']->get($record->id));
        $filters = $request->validate(['status' => ['nullable', Rule::in(['open', 'completed'])]]);
        $waits->decorate($record->newCollection([$record]), $request->user());
        $tasks = Task::forUser($request->user())->with('milestone')->where('project_id', $record->id)
            ->when(($filters['status'] ?? 'open') === 'completed', fn (Builder $q) => $q->whereNotNull('completed_at'), fn (Builder $q) => $q->whereNull('completed_at'))
            ->orderByRaw('due_date IS NULL')->orderBy('due_date')->orderBy('priority')->orderByDesc('id')->paginate(20)->withQueryString();
        $waits->decorate($tasks->getCollection(), $request->user());

        return Inertia::render('Work/Project', [
            'activity' => ActivityLog::forUser($request->user())->where('subject_type', 'project')->where('subject_id', $record->id)->orderByDesc('occurred_at')->orderByDesc('id')->paginate(10, ['*'], 'activity_page')->withQueryString(),
            'project' => $record, 'options' => $options->forUser($request->user()), 'filters' => $filters,
            'tasks' => $tasks,
        ]);
    }

    public function intake(Request $request, WorkOptions $options, WaitTracking $waits): Response
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'review' => ['nullable', 'boolean']]);
        $tasks = Task::forUser($request->user())->whereNull('completed_at')
            ->whereHas('domain', fn (Builder $q) => $q->forUser($request->user())->where('is_inbox', true))
            ->with('project')->orderByDesc('id')->paginate(20)->withQueryString();
        $waits->decorate($tasks->getCollection(), $request->user());

        return Inertia::render('Work/Intake', [
            'filters' => $filters,
            'aiEnabled' => app(CaptureService::class)->enabled(),
            'captures' => Capture::forUser($request->user())
                ->when($filters['q'] ?? null, fn ($q, $text) => $q->where('raw_text', 'like', '%'.$text.'%'))
                ->when($filters['review'] ?? false, fn ($q) => $q->whereIn('status', ['needs_triage', 'partially_executed', 'failed']))
                ->withCount('items')->latest('id')->paginate(10, ['*'], 'captures_page')->withQueryString(),
            'options' => $options->forUser($request->user()),
            'tasks' => $tasks,
        ]);
    }

    public function ideas(Request $request, WorkOptions $options): Response
    {
        return Inertia::render('Work/Ideas', [
            'options' => $options->forUser($request->user()),
            'ideas' => Note::forUser($request->user())->where('kind', 'thought')->latest('id')->paginate(20)->withQueryString(),
            'someday' => Project::forUser($request->user())->where('lifecycle', 'someday')->with('domain')->latest('id')->paginate(12, ['*'], 'someday_page')->withQueryString(),
        ]);
    }

    public function settings(Request $request, WorkOptions $options): Response
    {
        return Inertia::render('Work/Settings', [
            'options' => $options->forUser($request->user()),
            'captureTokens' => CaptureToken::forUser($request->user())->latest('id')->get(['id', 'label', 'device_name', 'rate_limit_per_hour', 'last_used_at', 'revoked_at', 'created_at']),
            'captureEndpoint' => route('capture.api', ['wait' => 1]),
            'parserStatus' => ['keyConfigured' => filled(config('chart.capture.key')), 'enabled' => app(CaptureService::class)->enabled(), 'model' => config('chart.capture.model')],
        ]);
    }

    public function timezone(Request $request): RedirectResponse
    {
        $data = $request->validate(['timezone' => ['required', 'timezone']]);
        AppSetting::forUser($request->user())->updateOrCreate(['key' => 'timezone'], ['user_id' => $request->user()->id, 'value' => $data['timezone']]);

        return back()->with('message', 'Timezone updated.');
    }
}
