<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveWorkWaitRequest;
use App\Services\WaitTracking;
use Illuminate\Http\RedirectResponse;

class WorkWaitController extends Controller
{
    public function task(SaveWorkWaitRequest $request, int $task, WaitTracking $waits): RedirectResponse
    {
        $waits->save($request->user(), 'task', $task, $request->validated());

        return back()->with('message', $request->boolean('waiting') ? 'Task hand-off saved.' : 'Task is back with you.');
    }

    public function project(SaveWorkWaitRequest $request, int $project, WaitTracking $waits): RedirectResponse
    {
        $waits->save($request->user(), 'project', $project, $request->validated());

        return back()->with('message', $request->boolean('waiting') ? 'Project hand-off saved.' : 'Project is back with you.');
    }
}
