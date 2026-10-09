<?php

namespace App\Http\Controllers;

use App\Http\Requests\DailyPlanTasksRequest;
use App\Http\Requests\SaveDailyPlanRequest;
use App\Services\DailyPlanning;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class DailyPlanController extends Controller
{
    public function tasks(DailyPlanTasksRequest $request, DailyPlanning $planning): JsonResponse
    {
        $tasks = $planning->activeTasks($request->user())->whereNull('completed_at')
            ->when($request->validated('q'), fn ($query, $text) => $query->where('title', 'like', '%'.$text.'%'))
            ->with(['domain:id,name', 'project:id,name'])
            ->orderByRaw('due_date IS NULL')->orderBy('due_date')->orderBy('priority')->orderBy('id')
            ->paginate(15, ['id', 'title', 'domain_id', 'project_id', 'due_date', 'priority']);

        return response()->json($tasks);
    }

    public function update(SaveDailyPlanRequest $request, DailyPlanning $planning): RedirectResponse
    {
        $planning->save($request->user(), $request->validated());

        return back()->with('message', 'Daily plan saved.');
    }
}
