<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResolveCaptureItemRequest;
use App\Http\Requests\StoreCaptureRequest;
use App\Models\Capture;
use App\Models\CaptureAttempt;
use App\Models\CaptureItem;
use App\Models\DailyPlan;
use App\Models\Task;
use App\Services\CaptureActions;
use App\Services\CaptureService;
use App\Services\DailyPlanning;
use App\Services\WorkOptions;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CaptureController extends Controller
{
    public function store(StoreCaptureRequest $request, CaptureService $service): JsonResponse
    {
        $capture = $service->receive($request->user(), $request->validated());

        return response()->json($service->confirmation($capture), 202);
    }

    public function show(Request $request, int $capture, WorkOptions $options): Response
    {
        $record = Capture::forUser($request->user())->with(['items' => fn ($q) => $q->forUser($request->user())->orderBy('sequence')])->findOrFail($capture);

        $workOptions = $options->forUser($request->user());
        $workOptions['captureTimezone'] = $record->timezone;
        $workOptions['tasks'] = app(DailyPlanning::class)->activeTasks($request->user())->whereNull('completed_at')->orderBy('title')->get(['id', 'title', 'project_id', 'domain_id', 'due_date', 'revision', 'wait_revision', 'waiting_on_person_id', 'wait_expected_by', 'parent_task_id', 'milestone_id']);

        $planDates = [$workOptions['today'], CarbonImmutable::parse($workOptions['today'])->addDay()->toDateString()];
        $plans = DailyPlan::forUser($request->user())->whereIn('plan_date', $planDates)->get()->keyBy(fn ($plan) => $plan->plan_date->toDateString());
        $names = Task::forUser($request->user())->whereIn('id', $plans->flatMap(fn ($plan) => $plan->top_task_ids)->unique())->pluck('title', 'id');
        $workOptions['plans'] = collect($planDates)->map(fn ($date) => [
            'plan_date' => $date, 'revision' => $plans->get($date)?->revision ?? 0,
            'top_task_names' => collect($plans->get($date)?->top_task_ids ?? [])->map(fn ($id) => $names->get($id, 'Unavailable task'))->all(),
            'tomorrow_focus' => $plans->get($date)?->tomorrow_focus,
        ]);
        $workOptions['tomorrow'] = $planDates[1];

        return Inertia::render('Work/Capture', [
            'capture' => $record, 'options' => $workOptions,
            'attempts' => CaptureAttempt::forUser($request->user())->where('capture_id', $record->id)->get(['id', 'model', 'status', 'input_tokens', 'output_tokens', 'created_at']),
            'aiEnabled' => app(CaptureService::class)->enabled(),
        ]);
    }

    public function retry(Request $request, int $capture, CaptureService $service): RedirectResponse
    {
        $service->retry(Capture::forUser($request->user())->findOrFail($capture));

        return back()->with('message', 'Saved capture queued for another attempt.');
    }

    public function resolve(ResolveCaptureItemRequest $request, int $item, CaptureActions $actions, CaptureService $service): RedirectResponse
    {
        $record = CaptureItem::forUser($request->user())->findOrFail($item);
        $actions->execute($record, $request->validated() + ['confidence' => 1, 'excerpt' => $record->excerpt]);
        $service->summarize(Capture::forUser($request->user())->findOrFail($record->capture_id));

        return back()->with('message', $record->fresh()->status === 'executed' ? 'Item filed.' : 'Item still needs review. Your original words are safe.');
    }

    public function retryItem(Request $request, int $item, CaptureActions $actions, CaptureService $service): RedirectResponse
    {
        $record = CaptureItem::forUser($request->user())->findOrFail($item);
        $actions->execute($record);
        $service->summarize(Capture::forUser($request->user())->findOrFail($record->capture_id));

        return back()->with('message', $record->fresh()->status === 'executed' ? 'Item filed.' : 'Item still needs review.');
    }

    public function undo(Request $request, int $item, CaptureActions $actions, CaptureService $service): RedirectResponse
    {
        $record = CaptureItem::forUser($request->user())->findOrFail($item);
        $actions->undo($record);
        $service->summarize(Capture::forUser($request->user())->findOrFail($record->capture_id));

        return back()->with('message', 'Filing undone. Your original capture is still here.');
    }
}
