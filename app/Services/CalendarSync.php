<?php

namespace App\Services;

use App\Exceptions\CalendarFailure;
use App\Models\CalendarConnection;
use App\Models\CalendarEvent;
use App\Models\ConnectedCalendar;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CalendarSync
{
    public function discover(CalendarConnection $connection): void
    {
        $result = app(GoogleCalendarClient::class)->pages($connection, 'users/me/calendarList', ['maxResults' => 250, 'showHidden' => 'true']);
        DB::transaction(function () use ($connection, $result) {
            User::whereKey($connection->user_id)->lockForUpdate()->firstOrFail();
            $current = CalendarConnection::forUser($connection->user_id)->findOrFail($connection->id);
            if ($current->revision !== $connection->revision || $current->status !== 'connected') {
                return;
            }
            if ($current->error) {
                $current->update(['error' => null]);
            }
            $keys = [];
            foreach ($result['items'] as $remote) {
                if (! is_string($remote['id'] ?? null) || ($remote['deleted'] ?? false)) {
                    continue;
                }
                $key = hash('sha256', $remote['id']);
                $keys[] = $key;
                $calendar = ConnectedCalendar::forUser($connection->user_id)->firstOrNew(['calendar_connection_id' => $connection->id, 'google_key' => $key]);
                $role = $remote['accessRole'] ?? 'none';
                $mode = $calendar->mode ?? 'off';
                if (! in_array($role, ['owner', 'writer', 'reader'], true)) {
                    $mode = 'off';
                } elseif ($mode === 'two_way' && ! in_array($role, ['owner', 'writer'], true)) {
                    $mode = 'read_only';
                }
                $calendar->fill(['user_id' => $connection->user_id, 'google_id' => $remote['id'], 'name' => Str::limit($remote['summaryOverride'] ?? $remote['summary'] ?? 'Calendar', 250, ''), 'timezone' => $remote['timeZone'] ?? 'UTC', 'access_role' => $role, 'is_primary' => $remote['primary'] ?? false, 'mode' => $mode]);
                if ($calendar->isDirty()) {
                    $calendar->revision = ($calendar->revision ?? 0) + 1;
                    $calendar->save();
                }
            }
            ConnectedCalendar::forUser($connection->user_id)->where('calendar_connection_id', $connection->id)->whereNotIn('google_key', $keys)->update(['mode' => 'off', 'access_role' => 'none', 'revision' => DB::raw('revision + 1'), 'error' => 'Calendar no longer available in Google.']);
        });
    }

    public function sync(int $userId, int $calendarId): void
    {
        $calendar = ConnectedCalendar::forUser($userId)->with(['connection' => fn ($q) => $q->forUser($userId)])->find($calendarId);
        if (! $calendar || $calendar->mode === 'off' || $calendar->connection?->status !== 'connected') {
            return;
        }
        $baseline = CalendarEvent::forUser($userId)->where('connected_calendar_id', $calendarId)->pluck('revision', 'google_key');
        $calendar->update(['last_attempt_at' => now()]);
        $client = app(GoogleCalendarClient::class);
        $path = 'calendars/'.rawurlencode($calendar->google_id).'/events';
        $parameters = ['maxResults' => 2500, 'singleEvents' => 'false', 'showDeleted' => 'true'];
        $full = ! $calendar->sync_token;
        try {
            try {
                $result = $client->pages($calendar->connection, $path, $parameters + ($full ? ['timeMin' => now()->subDays(30)->startOfDay()->toRfc3339String()] : ['syncToken' => $calendar->sync_token]));
            } catch (CalendarFailure $exception) {
                if ($exception->httpStatus !== 410) {
                    throw $exception;
                }
                $full = true;
                $result = $client->pages($calendar->connection, $path, $parameters + ['timeMin' => now()->subDays(30)->startOfDay()->toRfc3339String()]);
            }
            if (! is_string($result['sync_token']) || $result['sync_token'] === '') {
                throw new CalendarFailure;
            }
            // Expand recurring occurrences in a bounded rolling window; never expand an infinite series during token sync.
            $window = $client->pages($calendar->connection, $path, ['maxResults' => 2500, 'singleEvents' => 'true', 'showDeleted' => 'false', 'timeMin' => now()->subDays(7)->startOfDay()->toRfc3339String(), 'timeMax' => now()->addDays(90)->endOfDay()->toRfc3339String()]);
            DB::transaction(function () use ($calendar, $result, $window, $full, $userId, $baseline) {
                User::whereKey($userId)->lockForUpdate()->firstOrFail();
                $current = ConnectedCalendar::forUser($userId)->with('connection')->findOrFail($calendar->id);
                if ($current->revision !== $calendar->revision || $current->sync_token !== $calendar->sync_token || $current->mode === 'off' || $current->connection->status !== 'connected' || $current->connection->revision !== $calendar->connection->revision) {
                    return;
                }
                $records = collect($result['items'])->filter(fn ($item) => is_array($item) && is_string($item['id'] ?? null))->keyBy('id');
                foreach ($window['items'] as $item) {
                    if (is_array($item) && is_string($item['id'] ?? null)) {
                        $records->put($item['id'], $item);
                    }
                }
                $keys = $records->keys()->map(fn ($id) => hash('sha256', $id));
                $query = CalendarEvent::forUser($userId)->where('connected_calendar_id', $calendar->id)->whereNotNull('etag')->whereNull('removed_at')->whereNotIn('google_key', $keys);
                if (! $full) {
                    $query->whereNotNull('recurring_event_id');
                }
                $missingIds = $query->get(['id', 'google_key', 'revision'])->filter(fn ($event) => $baseline->get($event->google_key) === $event->revision)->pluck('id');
                CalendarEvent::forUser($userId)->whereIn('id', $missingIds)->update(['removed_at' => now(), 'revision' => DB::raw('revision + 1')]);
                $existing = CalendarEvent::forUser($userId)->where('connected_calendar_id', $calendar->id)->whereIn('google_key', $keys)->get()->keyBy('google_key');
                $rows = [];
                foreach ($records as $remote) {
                    $event = $existing->get(hash('sha256', $remote['id'])) ?? new CalendarEvent;
                    if ($event->exists && $baseline->get($event->google_key) !== $event->revision) {
                        continue;
                    }
                    $this->fill($event, $calendar, $remote);
                    $attributes = $event->getAttributes();
                    unset($attributes['id']);
                    $rows[] = $attributes;
                }
                if ($rows) {
                    foreach (array_chunk($rows, 100) as $chunk) {
                        CalendarEvent::query()->upsert($chunk, ['connected_calendar_id', 'google_key'], ['etag', 'title', 'description', 'location', 'starts_at', 'ends_at', 'start_date', 'end_date', 'all_day', 'is_series', 'recurring_event_id', 'status', 'remote_payload', 'synced_at', 'removed_at', 'revision', 'updated_at']);
                    }
                }
                $current->update(['sync_token' => $result['sync_token'], 'last_synced_at' => now(), 'error' => null]);
            });
        } catch (CalendarFailure $exception) {
            ConnectedCalendar::forUser($userId)->whereKey($calendarId)->update(['error' => $exception->getMessage()]);
        }
    }

    public function fill(CalendarEvent $event, ConnectedCalendar $calendar, array $remote): void
    {
        $allDay = isset($remote['start']['date']);
        $cancelled = ($remote['status'] ?? '') === 'cancelled';
        if (! $cancelled && ((! isset($remote['start']['dateTime']) && ! $allDay) || (! isset($remote['end']['dateTime']) && ! isset($remote['end']['date'])))) {
            throw new CalendarFailure;
        }
        $original = $event->remote_payload;
        $event->fill([
            'user_id' => $calendar->user_id, 'connected_calendar_id' => $calendar->id,
            'google_id' => $remote['id'], 'google_key' => hash('sha256', $remote['id']), 'etag' => $remote['etag'] ?? null,
            'title' => Str::limit($remote['summary'] ?? $event->title ?? 'Untitled event', 250, ''), 'description' => $remote['description'] ?? null, 'location' => $remote['location'] ?? null,
            'starts_at' => isset($remote['start']['dateTime']) ? CarbonImmutable::parse($remote['start']['dateTime'])->utc() : null,
            'ends_at' => isset($remote['end']['dateTime']) ? CarbonImmutable::parse($remote['end']['dateTime'])->utc() : null,
            'start_date' => $remote['start']['date'] ?? null, 'end_date' => $remote['end']['date'] ?? null,
            'all_day' => $allDay, 'is_series' => ! empty($remote['recurrence']), 'recurring_event_id' => $remote['recurringEventId'] ?? null,
            'status' => $remote['status'] ?? 'confirmed', 'origin' => $event->origin ?? 'google', 'remote_payload' => $remote,
            'synced_at' => now(), 'removed_at' => null, 'revision' => ($event->revision ?? 0) + ($original != $remote ? 1 : 0),
            'created_at' => $event->created_at ?? now(), 'updated_at' => now(),
        ]);
    }

    public function store(ConnectedCalendar $calendar, array $remote): CalendarEvent
    {
        $event = CalendarEvent::forUser($calendar->user_id)->firstOrNew(['connected_calendar_id' => $calendar->id, 'google_key' => hash('sha256', $remote['id'])]);
        $this->fill($event, $calendar, $remote);
        $event->save();

        return $event;
    }
}
