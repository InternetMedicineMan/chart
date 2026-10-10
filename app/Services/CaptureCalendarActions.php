<?php

namespace App\Services;

use App\Models\ActionLog;
use App\Models\CalendarMutation;
use App\Models\Capture;
use App\Models\CaptureItem;
use App\Models\ConnectedCalendar;
use App\Models\FeedNotification;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CaptureCalendarActions
{
    public function calendars(User $user): Builder
    {
        return ConnectedCalendar::forUser($user)->where('mode', 'two_way')->whereIn('access_role', ['owner', 'writer'])
            ->whereHas('connection', fn ($query) => $query->forUser($user)->where('status', 'connected'));
    }

    public function prepare(User $user, Capture $capture, array $data, array $chosen = [], bool $reviewed = false): array
    {
        $timezone = app(LocalDate::class)->timezone($user);
        if ($data['confidence'] < .8 || (! $reviewed && $capture->timezone !== $timezone)) {
            throw ValidationException::withMessages(['calendar' => 'Review the calendar and time in your current Chart timezone before scheduling.']);
        }
        $calendars = $this->calendars($user)->get();
        if (! empty($chosen['calendar_id'])) {
            $calendar = $calendars->firstWhere('id', (int) $chosen['calendar_id']);
            if (! $calendar || $calendar->revision !== (int) ($chosen['calendar_revision'] ?? -1)) {
                throw ValidationException::withMessages(['calendar' => 'Calendar access changed. Reload and select it again.']);
            }
        } elseif (! empty($data['calendar_ref'])) {
            $matches = $calendars->filter(fn ($candidate) => Str::lower(Str::squish($candidate->name)) === Str::lower(Str::squish($data['calendar_ref'])));
            $calendar = $matches->count() === 1 ? $matches->first() : null;
        } else {
            $primary = $calendars->where('is_primary', true);
            $calendar = $calendars->count() === 1 ? $calendars->first() : ($primary->count() === 1 ? $primary->first() : null);
        }
        if (! $calendar) {
            throw ValidationException::withMessages(['calendar' => 'Choose an available two-way calendar before scheduling this event.']);
        }
        $start = $data['event_start'] ?? null;
        if (! $start) {
            throw ValidationException::withMessages(['event_start' => 'Choose a start date and time.']);
        }
        $input = ['calendar_id' => $calendar->id, 'title' => $data['title'], 'description' => $data['body'] ?? '', 'location' => $data['location'] ?? '', 'all_day' => false,
            'starts_at' => $start, 'ends_at' => $data['event_end'] ?? CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $start, $timezone)->addHour()->format('Y-m-d\TH:i')];
        $payload = app(CalendarWriting::class)->payload($user, $input);
        if (CarbonImmutable::parse($payload['start']['dateTime'])->lte(now())) {
            throw ValidationException::withMessages(['event_start' => 'This start time has passed. Review the date before scheduling.']);
        }

        return $input;
    }

    public function execute(User $user, Capture $capture, array $data, array $chosen, bool $reviewed): array
    {
        $input = $this->prepare($user, $capture, $data, $chosen, $reviewed);
        $mutation = app(CalendarWriting::class)->save($user, $input + ['request_key' => Str::uuid()->toString()]);

        return [$mutation, 'calendar_mutation', null];
    }

    public function undo(User $user, ActionLog $log): bool
    {
        $mutation = CalendarMutation::forUser($user)->findOrFail($log->target_id);
        if ($mutation->status === 'discarded') {
            return true;
        }
        if ($mutation->status === 'applied') {
            app(CalendarWriting::class)->undo($user, $mutation->id);

            return CalendarMutation::forUser($user)->where('undo_of_id', $mutation->id)->where('status', 'applied')->exists();
        }
        app(CalendarWriting::class)->discard($user, $mutation->id);

        return true;
    }

    public function reconcile(int $userId): void
    {
        DB::transaction(function () use ($userId) {
            $user = User::whereKey($userId)->lockForUpdate()->first();
            if (! $user) {
                return;
            }
            $logs = ActionLog::forUser($user)->where('target_type', 'calendar_mutation')->where('status', 'ok')->get();
            $mutations = CalendarMutation::forUser($user)->whereIn('id', $logs->pluck('target_id'))->get()->keyBy('id');
            $undos = CalendarMutation::forUser($user)->whereIn('undo_of_id', $logs->pluck('target_id'))->get()->keyBy('undo_of_id');
            foreach ($logs as $log) {
                $mutation = $mutations->get($log->target_id);
                $undo = $undos->get($log->target_id);
                if (! $mutation) {
                    continue;
                }
                if ($mutation->status === 'discarded' || $undo?->status === 'applied') {
                    $log->update(['status' => 'undone', 'undone_at' => now()]);
                    CaptureItem::forUser($user)->whereKey($log->capture_item_id)->update(['status' => 'undone', 'undone_at' => now()]);
                    app(CaptureNotifications::class)->undone($log);
                    app(CaptureService::class)->summarize(Capture::forUser($user)->findOrFail($log->capture_id));
                } else {
                    $title = $undo ? (in_array($undo->status, ['failed', 'conflict']) ? 'Calendar undo needs attention' : 'Calendar undo queued') : match ($mutation->status) {
                        'applied' => 'Calendar event confirmed', 'failed', 'conflict' => 'Calendar event needs attention', default => 'Calendar event queued',
                    };
                    FeedNotification::forUser($user)->where('action_log_id', $log->id)->update(['title' => $title]);
                }
            }
        });
    }
}
