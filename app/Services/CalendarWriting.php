<?php

namespace App\Services;

use App\Exceptions\CalendarFailure;
use App\Jobs\ApplyCalendarMutation;
use App\Models\CalendarEvent;
use App\Models\CalendarMutation;
use App\Models\ConnectedCalendar;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CalendarWriting
{
    public function payload(User $user, array $data): array
    {
        $timezone = app(LocalDate::class)->timezone($user);
        if ($data['all_day']) {
            $start = ['date' => $data['start_date'], 'dateTime' => null, 'timeZone' => null];
            $end = ['date' => CarbonImmutable::parse($data['end_date'])->addDay()->toDateString(), 'dateTime' => null, 'timeZone' => null];
        } else {
            $parse = function (string $input) use ($timezone): CarbonImmutable {
                $time = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $input, $timezone);
                if ($time->format('Y-m-d\TH:i') !== $input) {
                    throw ValidationException::withMessages(['starts_at' => 'This local time does not exist because the clock changes. Choose another time.']);
                }
                // Require an unambiguous local time rather than silently choosing one side of a fall-back hour.
                if ($time->addHour()->format('Y-m-d\TH:i') === $input || $time->subHour()->format('Y-m-d\TH:i') === $input) {
                    throw ValidationException::withMessages(['starts_at' => 'This local time occurs twice because the clock changes. Edit this event in Google with an explicit timezone offset.']);
                }

                return $time;
            };
            $startTime = $parse($data['starts_at']);
            $endTime = $parse($data['ends_at']);
            if ($endTime->lte($startTime)) {
                throw ValidationException::withMessages(['ends_at' => 'The end must be after the start.']);
            }
            $start = ['date' => null, 'dateTime' => $startTime->toRfc3339String(), 'timeZone' => $timezone];
            $end = ['date' => null, 'dateTime' => $endTime->toRfc3339String(), 'timeZone' => $timezone];
        }

        return ['summary' => $data['title'], 'description' => $data['description'] ?? '', 'location' => $data['location'] ?? '', 'start' => $start, 'end' => $end];
    }

    private function writable(User $user, int $calendarId): ConnectedCalendar
    {
        $calendar = ConnectedCalendar::forUser($user)->with(['connection' => fn ($q) => $q->forUser($user)])->findOrFail($calendarId);
        if ($calendar->mode !== 'two_way' || ! in_array($calendar->access_role, ['owner', 'writer'], true) || $calendar->connection?->status !== 'connected') {
            throw ValidationException::withMessages(['calendar' => 'Enable two-way access on an available calendar before saving an event.']);
        }

        return $calendar;
    }

    public function save(User $user, array $data, ?int $eventId = null): CalendarMutation
    {
        $mutation = DB::transaction(function () use ($user, $data, $eventId) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = CalendarMutation::forUser($user)->where('request_key', $data['request_key'])->first();
            $payload = $this->payload($user, $data);
            if ($existing) {
                if ($existing->connected_calendar_id !== (int) $data['calendar_id'] || $existing->payload !== $payload || $existing->operation !== ($eventId ? 'update' : 'create') || ($eventId && $existing->calendar_event_id !== $eventId)) {
                    throw ValidationException::withMessages(['request_key' => 'This request already belongs to a different event change. Reload before saving.']);
                }

                return $existing;
            }
            $calendar = $this->writable($user, (int) $data['calendar_id']);
            $event = $eventId ? CalendarEvent::forUser($user)->where('connected_calendar_id', $calendar->id)->findOrFail($eventId) : null;
            if ($event) {
                if ($event->revision !== (int) ($data['revision'] ?? -1) || ! $event->etag || $event->status === 'cancelled' || $event->removed_at || $event->is_series || ($event->remote_payload['eventType'] ?? 'default') !== 'default') {
                    throw ValidationException::withMessages(['event' => 'This event changed or must be edited in Google. Reload its latest version.']);
                }
                $this->assertNoPending($user, $event);
            } else {
                $googleId = str_replace('-', '', Str::uuid()->toString());
                $event = new CalendarEvent(['origin' => 'app']);
                app(CalendarSync::class)->fill($event, $calendar, $payload + ['id' => $googleId]);
                $event->synced_at = null;
                $event->save();
            }

            return CalendarMutation::create([
                'user_id' => $user->id, 'connected_calendar_id' => $calendar->id, 'calendar_event_id' => $event->id,
                'request_key' => $data['request_key'], 'operation' => $eventId ? 'update' : 'create', 'payload' => $payload,
                'before_payload' => $eventId ? $this->editableFields($event->remote_payload) : null,
                'expected_etag' => $event->etag, 'available_at' => now(),
            ]);
        });
        $this->dispatch($mutation);

        return $mutation;
    }

    public function dispatch(CalendarMutation $mutation): void
    {
        if ($mutation->status !== 'pending') {
            return;
        }
        try {
            if (config('queue.connections.'.config('chart.calendar.queue_connection').'.driver') === 'sync') {
                throw new \RuntimeException('Calendar requires an asynchronous queue.');
            }
            Bus::dispatch((new ApplyCalendarMutation($mutation->user_id, $mutation->id))->onConnection(config('chart.calendar.queue_connection'))->onQueue(config('chart.calendar.queue')));
        } catch (Throwable) {
            $mutation->update(['error' => 'Saved in Chart. Waiting for the calendar worker; the scheduler will retry.']);
        }
    }

    private function assertNoPending(User $user, CalendarEvent $event): void
    {
        if (CalendarMutation::forUser($user)->where('calendar_event_id', $event->id)->whereIn('status', ['pending', 'conflict', 'failed'])->exists()) {
            throw ValidationException::withMessages(['event' => 'Resolve or discard the existing saved change before editing this event again.']);
        }
    }

    private function editableFields(array $payload): array
    {
        return ['summary' => $payload['summary'] ?? 'Untitled event', 'description' => $payload['description'] ?? '', 'location' => $payload['location'] ?? '', 'start' => $payload['start'], 'end' => $payload['end']];
    }

    public function apply(int $userId, int $mutationId): void
    {
        // Persist attempt intent before any Google call. A killed worker must not make an uncertain write look unsent.
        $claimed = DB::transaction(function () use ($userId, $mutationId) {
            $user = User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $mutation = CalendarMutation::forUser($user)->find($mutationId);
            if (! $mutation || $mutation->status !== 'pending' || $mutation->available_at?->isFuture()) {
                return false;
            }
            try {
                $this->writable($user, $mutation->connected_calendar_id);
            } catch (ValidationException $exception) {
                $mutation->update(['status' => 'failed', 'error' => collect($exception->errors())->flatten()->first()]);

                return false;
            }
            $mutation->increment('attempts');

            return true;
        });
        if (! $claimed) {
            return;
        }
        DB::transaction(function () use ($userId, $mutationId) {
            $user = User::whereKey($userId)->lockForUpdate()->firstOrFail();
            $mutation = CalendarMutation::forUser($user)->lockForUpdate()->find($mutationId);
            if (! $mutation || $mutation->status !== 'pending' || $mutation->available_at?->isFuture()) {
                return;
            }
            $event = CalendarEvent::forUser($user)->findOrFail($mutation->calendar_event_id);
            try {
                $calendar = $this->writable($user, $mutation->connected_calendar_id);
                $client = app(GoogleCalendarClient::class);
                $path = 'calendars/'.rawurlencode($calendar->google_id).'/events';
                $eventPath = $path.'/'.rawurlencode($event->google_id);
                $remote = null;
                // Every attempt checks the stable identity, including recovery after an uncertain response.
                try {
                    $remote = $client->request($calendar->connection, 'GET', $eventPath);
                } catch (CalendarFailure $exception) {
                    if (! in_array($exception->httpStatus, [404, 410], true)) {
                        throw $exception;
                    }
                }
                $alreadyApplied = $remote && ($remote['extendedProperties']['private']['chartMutation'] ?? null) === $mutation->request_key && $this->matches($remote, $mutation->payload);
                if ($mutation->operation === 'delete') {
                    if ($remote && ($remote['status'] ?? '') !== 'cancelled') {
                        if (($remote['etag'] ?? null) !== $mutation->expected_etag) {
                            throw new CalendarFailure(412);
                        }
                        $client->request($calendar->connection, 'DELETE', $eventPath.'?sendUpdates=all', [], $mutation->expected_etag);
                    }
                    $event->update(['status' => 'cancelled', 'removed_at' => now(), 'revision' => $event->revision + 1]);
                    $mutation->update(['status' => 'applied', 'applied_at' => now(), 'error' => null]);

                    return;
                }
                if (! $alreadyApplied) {
                    $body = $mutation->payload;
                    $body['extendedProperties']['private'] = array_merge($remote['extendedProperties']['private'] ?? [], ['chartMutation' => $mutation->request_key]);
                    if ($mutation->operation === 'create') {
                        if ($remote) {
                            throw new CalendarFailure(409);
                        }
                        $remote = $client->request($calendar->connection, 'POST', $path.'?sendUpdates=all', $body + ['id' => $event->google_id]);
                    } else {
                        if (! $remote || ($remote['status'] ?? '') === 'cancelled' || ($remote['etag'] ?? null) !== $mutation->expected_etag) {
                            throw new CalendarFailure(412);
                        }
                        $remote = $client->request($calendar->connection, 'PATCH', $eventPath.'?sendUpdates=all&conferenceDataVersion=1', $body, $mutation->expected_etag);
                    }
                }
                if (! is_string($remote['id'] ?? null) || ! is_string($remote['etag'] ?? null) || $remote['id'] !== $event->google_id) {
                    throw new CalendarFailure;
                }
                app(CalendarSync::class)->store($calendar, $remote);
                $mutation->update(['status' => 'applied', 'applied_etag' => $remote['etag'], 'applied_at' => now(), 'error' => null]);
            } catch (ValidationException $exception) {
                $mutation->update(['status' => 'failed', 'error' => collect($exception->errors())->flatten()->first()]);
            } catch (CalendarFailure $exception) {
                if (in_array($exception->httpStatus, [409, 412], true) && ! empty($remote['id'])) {
                    try {
                        $latest = $client->request($calendar->connection, 'GET', $eventPath);
                        app(CalendarSync::class)->store($calendar, $latest);
                    } catch (CalendarFailure $refreshFailure) {
                        if (in_array($refreshFailure->httpStatus, [404, 410], true)) {
                            $event->update(['status' => 'cancelled', 'removed_at' => now(), 'revision' => $event->revision + 1]);
                        }
                    }
                } elseif ($exception->httpStatus === 412 && $remote === null) {
                    $event->update(['status' => 'cancelled', 'removed_at' => now(), 'revision' => $event->revision + 1]);
                }
                $retry = in_array($exception->httpStatus, [0, 429, 500, 502, 503, 504], true) && $mutation->attempts < 5;
                $mutation->update(['status' => $retry ? 'pending' : (in_array($exception->httpStatus, [409, 412], true) ? 'conflict' : 'failed'), 'error' => $exception->getMessage(), 'available_at' => now()->addMinutes(min(60, 2 ** $mutation->attempts))]);
            }
        });
    }

    private function matches(array $remote, array $payload): bool
    {
        foreach (['summary', 'description', 'location'] as $field) {
            if (($remote[$field] ?? '') !== ($payload[$field] ?? '')) {
                return false;
            }
        }
        foreach (['start', 'end'] as $field) {
            if (isset($payload[$field]['date'])) {
                if (($remote[$field]['date'] ?? null) !== $payload[$field]['date']) {
                    return false;
                }
            } elseif (! isset($remote[$field]['dateTime']) || ! CarbonImmutable::parse($remote[$field]['dateTime'])->equalTo(CarbonImmutable::parse($payload[$field]['dateTime']))) {
                return false;
            }
        }

        return true;
    }

    public function discard(User $user, int $mutationId): void
    {
        DB::transaction(function () use ($user, $mutationId) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $mutation = CalendarMutation::forUser($user)->findOrFail($mutationId);
            if (! in_array($mutation->status, ['pending', 'conflict', 'failed'], true)) {
                throw ValidationException::withMessages(['mutation' => 'This change has already been sent. Reload to see its result.']);
            }
            // An interrupted HTTP request may have succeeded. Require reconciliation before discarding it.
            if ($mutation->attempts > 0 && $mutation->status !== 'conflict') {
                throw ValidationException::withMessages(['mutation' => 'Google may have received this change. Wait for its retry before discarding.']);
            }
            $mutation->update(['status' => 'discarded']);
            if ($mutation->operation === 'create') {
                CalendarEvent::forUser($user)->whereKey($mutation->calendar_event_id)->whereNull('etag')->update(['removed_at' => now()]);
            }
        });
    }

    public function retry(User $user, int $mutationId): void
    {
        $mutation = DB::transaction(function () use ($user, $mutationId) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $mutation = CalendarMutation::forUser($user)->findOrFail($mutationId);
            if ($mutation->status !== 'failed') {
                throw ValidationException::withMessages(['mutation' => 'Only a failed request can be retried. Review conflicts by editing the latest event.']);
            }
            $this->writable($user, $mutation->connected_calendar_id);
            $mutation->update(['status' => 'pending', 'available_at' => now()]);

            return $mutation;
        });
        $this->dispatch($mutation);
    }

    public function undo(User $user, int $mutationId): void
    {
        $undo = DB::transaction(function () use ($user, $mutationId) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $mutation = CalendarMutation::forUser($user)->findOrFail($mutationId);
            $existing = CalendarMutation::forUser($user)->where('undo_of_id', $mutation->id)->first();
            if ($existing) {
                return $existing;
            }
            if ($mutation->status !== 'applied' || $mutation->operation === 'delete' || $mutation->undo_of_id || $mutation->applied_at->lt(now()->subDays(7))) {
                throw ValidationException::withMessages(['undo' => 'Undo is available for seven days after Google confirms this change.']);
            }
            $calendar = $this->writable($user, $mutation->connected_calendar_id);
            $event = CalendarEvent::forUser($user)->findOrFail($mutation->calendar_event_id);
            $this->assertNoPending($user, $event);
            if ($event->etag !== $mutation->applied_etag || $event->removed_at || $event->status === 'cancelled') {
                throw ValidationException::withMessages(['undo' => 'This event changed after the saved edit. Keep newer work by editing the current event.']);
            }

            return CalendarMutation::create(['user_id' => $user->id, 'connected_calendar_id' => $calendar->id, 'calendar_event_id' => $event->id, 'request_key' => Str::uuid()->toString(), 'operation' => $mutation->operation === 'create' ? 'delete' : 'update', 'payload' => $mutation->before_payload ?? [], 'expected_etag' => $mutation->applied_etag, 'available_at' => now(), 'undo_of_id' => $mutation->id]);
        });
        $this->dispatch($undo);
    }
}
