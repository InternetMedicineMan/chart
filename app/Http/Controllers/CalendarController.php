<?php

namespace App\Http\Controllers;

use App\Exceptions\CalendarFailure;
use App\Http\Requests\CalendarCallbackRequest;
use App\Http\Requests\CalendarRangeRequest;
use App\Http\Requests\SaveCalendarEventRequest;
use App\Http\Requests\SaveCalendarSelectionRequest;
use App\Jobs\SyncCalendar;
use App\Models\CalendarConnection;
use App\Models\CalendarMutation;
use App\Models\ConnectedCalendar;
use App\Models\User;
use App\Services\CalendarBriefing;
use App\Services\CalendarSync;
use App\Services\CalendarWriting;
use App\Services\GoogleCalendarClient;
use App\Services\LocalDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function settings(Request $request, GoogleCalendarClient $client): Response
    {
        return Inertia::render('Work/CalendarSettings', [
            'configured' => $client->configured(), 'callbackUrl' => $client->redirectUri(),
            'connection' => CalendarConnection::forUser($request->user())->first(),
            'calendars' => ConnectedCalendar::forUser($request->user())->orderByDesc('is_primary')->orderBy('name')->get(),
        ]);
    }

    public function connect(Request $request, GoogleCalendarClient $client): \Symfony\Component\HttpFoundation\Response
    {
        if (! $client->configured()) {
            throw ValidationException::withMessages(['calendar' => 'Configure the Google Calendar OAuth credentials first.']);
        }
        $state = Str::random(64);
        $request->session()->put('calendar_oauth', ['state' => hash('sha256', $state), 'user_id' => $request->user()->id, 'expires_at' => now()->addMinutes(10)->timestamp]);

        return Inertia::location($client->authorizationUrl($state));
    }

    public function callback(CalendarCallbackRequest $request, GoogleCalendarClient $client, CalendarSync $sync): RedirectResponse
    {
        $pending = $request->session()->pull('calendar_oauth');
        abort_unless(is_array($pending) && $pending['user_id'] === $request->user()->id && $pending['expires_at'] > now()->timestamp && hash_equals($pending['state'], hash('sha256', $request->validated('state'))), 403);
        if ($request->filled('error') || ! $request->filled('code')) {
            return redirect()->route('calendar.settings')->withErrors(['calendar' => 'Google connection was cancelled. Your Chart account is unchanged.']);
        }
        try {
            $tokens = $client->exchange($request->validated('code'));
            $identity = $client->identity($tokens['access_token']);
            $scopes = explode(' ', $tokens['scope'] ?? '');
            if (array_diff(array_slice(GoogleCalendarClient::SCOPES, 2), $scopes)) {
                throw ValidationException::withMessages(['calendar' => 'Grant both calendar-list and event access when connecting Google.']);
            }
            $connection = DB::transaction(function () use ($request, $tokens, $identity, $scopes) {
                User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $record = CalendarConnection::forUser($request->user())->firstOrNew(['user_id' => $request->user()->id]);
                if ($record->exists && $record->google_subject !== $identity['sub']) {
                    throw ValidationException::withMessages(['calendar' => 'Reconnect the same Google account. Support for switching accounts is not available yet.']);
                }
                $refresh = $tokens['refresh_token'] ?? $record->refresh_token;
                if (! $refresh) {
                    throw ValidationException::withMessages(['calendar' => 'Google did not supply offline access. Reconnect and approve access again.']);
                }
                $record->fill(['google_subject' => $identity['sub'], 'email' => $identity['email'], 'access_token' => $tokens['access_token'], 'refresh_token' => $refresh, 'expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600)), 'scopes' => $scopes, 'status' => 'connected', 'error' => null, 'revision' => ($record->revision ?? 0) + 1])->save();

                return $record;
            });
            $sync->discover($connection);
        } catch (ValidationException $exception) {
            return redirect()->route('calendar.settings')->withErrors($exception->errors());
        } catch (CalendarFailure $exception) {
            return redirect()->route('calendar.settings')->withErrors(['calendar' => $exception->getMessage()]);
        }

        return redirect()->route('calendar.settings')->with('message', 'Google connected. Choose which calendars Chart should use.');
    }

    public function refresh(Request $request, CalendarSync $sync): RedirectResponse
    {
        $connection = CalendarConnection::forUser($request->user())->where('status', 'connected')->firstOrFail();
        try {
            $sync->discover($connection);
        } catch (CalendarFailure $exception) {
            return back()->withErrors(['calendar' => $exception->getMessage()]);
        }
        ConnectedCalendar::forUser($request->user())->where('mode', '!=', 'off')->each(fn ($calendar) => $this->queue($calendar));

        return back()->with('message', 'Selected calendars queued for sync.');
    }

    public function selection(SaveCalendarSelectionRequest $request, int $calendar): RedirectResponse
    {
        $record = DB::transaction(function () use ($request, $calendar) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $record = ConnectedCalendar::forUser($request->user())->with('connection')->findOrFail($calendar);
            if ($record->revision !== $request->integer('revision') || $record->connection->status !== 'connected') {
                throw ValidationException::withMessages(['calendar' => 'Calendar access changed. Reload or reconnect before choosing access.']);
            }
            $mode = $request->validated('mode');
            if (($mode === 'two_way' && ! in_array($record->access_role, ['owner', 'writer'], true)) || ($mode !== 'off' && ! in_array($record->access_role, ['owner', 'writer', 'reader'], true))) {
                throw ValidationException::withMessages(['mode' => 'Google has not granted this level of calendar access.']);
            }
            if ($mode !== 'two_way' && CalendarMutation::forUser($request->user())->where('connected_calendar_id', $record->id)->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['calendar' => 'Wait for or discard queued calendar changes before changing access.']);
            }
            $record->update(['mode' => $mode, 'revision' => $record->revision + 1]);

            return $record;
        });
        if ($record->mode !== 'off') {
            $this->queue($record);
        }

        return back()->with('message', 'Calendar access saved.');
    }

    private function queue(ConnectedCalendar $calendar): void
    {
        try {
            if (config('queue.connections.'.config('chart.calendar.queue_connection').'.driver') === 'sync') {
                throw new \RuntimeException('Calendar requires an asynchronous queue.');
            }
            SyncCalendar::dispatch($calendar->user_id, $calendar->id)->onConnection(config('chart.calendar.queue_connection'))->onQueue(config('chart.calendar.queue'));
        } catch (\Throwable) {
            $calendar->update(['error' => 'Waiting for the calendar worker. The scheduler will retry.']);
        }
    }

    public function disconnect(Request $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $connection = CalendarConnection::forUser($request->user())->firstOrFail();
            if (CalendarMutation::forUser($request->user())->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['calendar' => 'Wait for or discard queued changes before disconnecting Google.']);
            }
            $connection->update(['status' => 'disconnected', 'access_token' => null, 'refresh_token' => null, 'revision' => $connection->revision + 1]);
            ConnectedCalendar::forUser($request->user())->update(['mode' => 'off', 'revision' => DB::raw('revision + 1')]);
        });

        return back()->with('message', 'Google disconnected from Chart. You can also revoke access in your Google account.');
    }

    public function index(CalendarRangeRequest $request, CalendarBriefing $briefing): Response
    {
        $user = $request->user();
        $timezone = app(LocalDate::class)->timezone($user);
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $start = CarbonImmutable::parse($request->validated('date') ?: $today->toDateString(), $timezone)->startOfDay();
        $events = $briefing->range($user, $start, $start->addDays(7))->with('calendar:id,name,mode,access_role')->orderByDesc('all_day')->orderBy('start_date')->orderBy('starts_at')->paginate(30, ['*'], 'events_page')->withQueryString()->through(fn ($event) => $event->toArray() + ['local_start' => $event->starts_at?->setTimezone($timezone)->format('Y-m-d\TH:i'), 'local_end' => $event->ends_at?->setTimezone($timezone)->format('Y-m-d\TH:i'), 'last_day' => $event->end_date?->copy()->subDay()->toDateString(), 'has_guests' => ! empty($event->remote_payload['attendees']), 'editable' => ! $event->is_series && ($event->remote_payload['eventType'] ?? 'default') === 'default' && filled($event->etag)]);
        $mutations = CalendarMutation::forUser($user)->latest('id')->paginate(10, ['*'], 'changes_page')->withQueryString();

        return Inertia::render('Work/Calendar', ['events' => $events, 'changes' => $mutations, 'calendars' => ConnectedCalendar::forUser($user)->where('mode', '!=', 'off')->orderBy('name')->get(['id', 'name', 'mode', 'access_role', 'last_synced_at', 'error']), 'date' => $start->toDateString(), 'minDate' => $today->subDays(7)->toDateString(), 'maxDate' => $today->addDays(83)->toDateString(), 'timezone' => $timezone, 'requestKey' => Str::uuid()->toString(), 'connection' => CalendarConnection::forUser($user)->first()]);
    }

    public function store(SaveCalendarEventRequest $request, CalendarWriting $writing): RedirectResponse
    {
        $writing->save($request->user(), $request->validated());

        return back()->with('message', 'Saved in Chart. Waiting for Google confirmation.');
    }

    public function update(SaveCalendarEventRequest $request, int $event, CalendarWriting $writing): RedirectResponse
    {
        $writing->save($request->user(), $request->validated(), $event);

        return back()->with('message', 'Change saved. Waiting for Google confirmation.');
    }

    public function discard(Request $request, int $mutation, CalendarWriting $writing): RedirectResponse
    {
        $writing->discard($request->user(), $mutation);

        return back();
    }

    public function retry(Request $request, int $mutation, CalendarWriting $writing): RedirectResponse
    {
        $writing->retry($request->user(), $mutation);

        return back()->with('message', 'Saved change queued for another attempt.');
    }

    public function undo(Request $request, int $mutation, CalendarWriting $writing): RedirectResponse
    {
        $writing->undo($request->user(), $mutation);

        return back()->with('message', 'Undo saved. Waiting for Google confirmation.');
    }
}
