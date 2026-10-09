<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveActivityRequest;
use App\Models\ActivityLog;
use App\Models\Domain;
use App\Models\Project;
use App\Services\ActivityTracking;
use App\Services\WorkOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ActivityController extends Controller
{
    public function index(Request $request, WorkOptions $options): Response
    {
        $filters = $request->validate(['subject_type' => ['nullable', Rule::in(['project', 'domain'])], 'subject_id' => ['nullable', 'integer', 'required_with:subject_type']]);
        $type = $filters['subject_type'] ?? null;
        $id = $filters['subject_id'] ?? null;
        $subject = null;
        if ($type && $id) {
            $class = $type === 'project' ? Project::class : Domain::class;
            $subject = $class::forUser($request->user())->findOrFail($id);
        }
        $entries = ActivityLog::forUser($request->user())
            ->when($subject && $type === 'domain', fn ($query) => $query->where('domain_id', $id))
            ->when($subject && $type === 'project', fn ($query) => $query->where('subject_type', 'project')->where('subject_id', $id))
            ->orderByDesc('occurred_at')->orderByDesc('id')->paginate(20)->withQueryString();

        return Inertia::render('Work/Activity', ['entries' => $entries, 'options' => $options->forUser($request->user()), 'filters' => $filters, 'subject' => $subject ? ['name' => $subject->name, 'id' => $subject->id, 'type' => $type] : null]);
    }

    public function store(SaveActivityRequest $request, ActivityTracking $tracking): RedirectResponse
    {
        $tracking->save($request->user(), $request->validated());

        return back()->with('message', 'Activity logged.');
    }

    public function update(SaveActivityRequest $request, int $activity, ActivityTracking $tracking): RedirectResponse
    {
        $tracking->save($request->user(), $request->validated(), $activity);

        return back()->with('message', 'Activity updated.');
    }

    public function destroy(SaveActivityRequest $request, int $activity, ActivityTracking $tracking): RedirectResponse
    {
        $tracking->delete($request->user(), $activity, $request->validated('revision'));

        return back()->with('message', 'Activity removed.');
    }
}
