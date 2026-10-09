<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateObservationRequest;
use App\Models\Observation;
use App\Models\User;
use App\Services\BriefingObservations;
use App\Services\LocalDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ObservationController extends Controller
{
    public function index(Request $request, BriefingObservations $observations): Response
    {
        $observations->refresh($request->user());
        $status = in_array($request->query('status'), ['snoozed', 'dismissed', 'resolved'], true) ? $request->query('status') : 'active';
        $query = Observation::forUser($request->user());
        match ($status) {
            'active' => $query->visible(),
            'snoozed' => $query->whereNull('resolved_at')->whereNull('dismissed_at')->where('expires_at', '>', now())->where('snoozed_until', '>', now()),
            'dismissed' => $query->whereNull('resolved_at')->where('expires_at', '>', now())->whereNotNull('dismissed_at'),
            'resolved' => $query->where(fn ($q) => $q->whereNotNull('resolved_at')->orWhere('expires_at', '<=', now())),
        };

        return Inertia::render('Work/Observations', ['observations' => $query->orderByDesc('score')->orderByDesc('id')->paginate(20)->withQueryString(), 'status' => $status]);
    }

    public function update(UpdateObservationRequest $request, int $observation): RedirectResponse
    {
        DB::transaction(function () use ($request, $observation) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $record = Observation::forUser($request->user())->findOrFail($observation);
            $changes = match ($request->validated('action')) {
                'dismiss' => ['dismissed_at' => now(), 'snoozed_until' => null],
                'snooze' => ['dismissed_at' => null, 'snoozed_until' => CarbonImmutable::now(app(LocalDate::class)->timezone($request->user()))->addDays($request->integer('days'))->utc()],
                'restore' => ['dismissed_at' => null, 'snoozed_until' => null],
            };
            $record->update($changes);
        });

        return back();
    }
}
