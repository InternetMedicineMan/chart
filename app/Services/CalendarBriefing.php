<?php

namespace App\Services;

use App\Models\CalendarConnection;
use App\Models\CalendarEvent;
use App\Models\ConnectedCalendar;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class CalendarBriefing
{
    public function range(User $user, CarbonImmutable $start, CarbonImmutable $end): Builder
    {
        return CalendarEvent::query()->visible($user)->where(fn ($q) => $q
            ->where(fn ($q) => $q->where('all_day', false)->where('starts_at', '<', $end->utc())->where('ends_at', '>', $start->utc()))
            ->orWhere(fn ($q) => $q->where('all_day', true)->where('start_date', '<', $end->toDateString())->where('end_date', '>', $start->toDateString())));
    }

    public function tomorrow(User $user): ?array
    {
        $connection = CalendarConnection::forUser($user)->where('status', 'connected')->first();
        $calendars = ConnectedCalendar::forUser($user)->where('mode', '!=', 'off')->get();
        if (! $connection || $calendars->isEmpty() || $calendars->contains(fn ($calendar) => ! $calendar->last_synced_at || $calendar->last_synced_at->lt(now()->subMinutes(30)) || $calendar->error)) {
            return null;
        }
        $start = CarbonImmutable::now(app(LocalDate::class)->timezone($user))->addDay()->startOfDay();
        $end = $start->addDay();
        $events = $this->range($user, $start, $end)->whereNotNull('etag')->get()->filter(function ($event) {
            return ($event->remote_payload['transparency'] ?? 'opaque') !== 'transparent' && ! collect($event->remote_payload['attendees'] ?? [])->contains(fn ($attendee) => ($attendee['self'] ?? false) && ($attendee['responseStatus'] ?? '') === 'declined');
        });
        $focusStart = $start->setTime(8, 0);
        $focusEnd = $start->setTime(17, 0);
        $intervals = $events->where('all_day', false)->map(fn ($event) => [max($focusStart->timestamp, $event->starts_at->timestamp), min($focusEnd->timestamp, $event->ends_at->timestamp)])->filter(fn ($interval) => $interval[1] > $interval[0])->sortBy(fn ($interval) => $interval[0]);
        $seconds = 0;
        $lastEnd = 0;
        foreach ($intervals as [$from, $to]) {
            $seconds += max(0, $to - max($from, $lastEnd));
            $lastEnd = max($lastEnd, $to);
        }

        return ['date' => $start->toDateString(), 'minutes' => (int) round($seconds / 60), 'all_day_count' => $events->where('all_day', true)->count(), 'focus_minutes' => $events->where('all_day', true)->isNotEmpty() ? 0 : (int) (($focusEnd->timestamp - $focusStart->timestamp - $seconds) / 60), 'window' => '8 a.m.–5 p.m.'];
    }

    public function forUser(User $user): ?array
    {
        $connection = CalendarConnection::forUser($user)->where('status', '!=', 'disconnected')->first();
        if (! $connection) {
            return null;
        }
        $calendars = ConnectedCalendar::forUser($user)->where('mode', '!=', 'off')->get();
        $timezone = app(LocalDate::class)->timezone($user);
        $now = CarbonImmutable::now($timezone);
        $today = $now->startOfDay();
        $events = $this->range($user, $today, $today->addDays(7))->whereNotNull('etag')->with('calendar:id,name')
            ->where(fn ($q) => $q->where('all_day', true)->orWhere('ends_at', '>', $now->utc()))->get()->reject(fn ($event) => collect($event->remote_payload['attendees'] ?? [])->contains(fn ($attendee) => ($attendee['self'] ?? false) && ($attendee['responseStatus'] ?? '') === 'declined'))
            ->sortBy(fn ($event) => $event->all_day ? CarbonImmutable::parse($event->start_date->toDateString(), $timezone)->timestamp : $event->starts_at->timestamp)->take(3)->values();

        return ['events' => $events, 'selected_count' => $calendars->count(), 'last_synced_at' => $calendars->min('last_synced_at'), 'stale' => $connection->status !== 'connected' || $calendars->contains(fn ($calendar) => ! $calendar->last_synced_at || $calendar->last_synced_at->lt(now()->subMinutes(30)) || $calendar->error), 'tomorrow' => $this->tomorrow($user)];
    }
}
