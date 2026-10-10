<?php

namespace App\Services;

use App\Jobs\SendBrowserPush;
use App\Models\CalendarEvent;
use App\Models\FeedNotification;
use App\Models\PushDelivery;
use App\Models\PushSubscription;
use App\Models\Reminder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReminderDelivery
{
    public function candidates(User $user): array
    {
        $result = [];
        $add = function (string $type, int $id, CarbonImmutable $at, array $offsets, string $title, string $url) use (&$result, $user) {
            foreach (array_unique($offsets) as $offset) {
                $scheduled = $at->subMinutes($offset)->utc();
                $key = hash('sha256', implode(':', [$user->id, $type, $id, $at->timestamp, $offset]));
                $result[$key] = ['subject_type' => $type, 'subject_id' => $id, 'scheduled_at' => $scheduled, 'title' => $title, 'target_url' => $url];
            }
        };
        $timezone = app(LocalDate::class)->timezone($user);
        $tasks = app(DailyPlanning::class)->activeTasks($user)->whereNull('completed_at')->whereNotNull('due_date')->whereNotNull('due_time')->get();
        foreach ($tasks as $task) {
            $input = $task->due_date->toDateString().'T'.substr($task->due_time, 0, 5);
            try {
                $at = app(LocalDate::class)->localTime($input, $task->recurrence_timezone ?: $timezone);
            } catch (ValidationException) {
                continue;
            }
            $add('task', $task->id, $at, $task->reminder_offsets ?? [0], $task->title, route('tasks.show', $task->id, false));
        }
        $events = CalendarEvent::visible($user)->where('all_day', false)->whereNotNull('etag')->whereNotNull('starts_at')->where('starts_at', '>=', now()->subDay())
            ->whereHas('calendar', fn ($q) => $q->whereNotNull('reminder_minutes')->where('last_synced_at', '>=', now()->subMinutes(30))->whereNull('error')->whereHas('connection', fn ($c) => $c->where('status', 'connected')))->with('calendar')->get();
        foreach ($events as $event) {
            if (collect($event->remote_payload['attendees'] ?? [])->contains(fn ($attendee) => ($attendee['self'] ?? false) && ($attendee['responseStatus'] ?? '') === 'declined')) {
                continue;
            }
            $add('event', $event->id, $event->starts_at, [$event->calendar->reminder_minutes], $event->title, route('calendar.index', [], false));
        }

        return $result;
    }

    public function run(User $user): void
    {
        DB::transaction(function () use ($user) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $candidates = $this->candidates($user);
            Reminder::forUser($user)->where('status', 'pending')->whereNotIn('dedup_key', array_keys($candidates))->update(['status' => 'cancelled']);
            $subscriptions = PushSubscription::forUser($user)->get();
            $existing = Reminder::forUser($user)->whereIn('dedup_key', array_keys($candidates))->get()->keyBy('dedup_key');
            foreach ($candidates as $key => $data) {
                $reminder = $existing->get($key);
                if (! $reminder && $data['scheduled_at']->lt(now()->subMinutes(5))) {
                    continue;
                }
                $reminder ??= Reminder::create(['user_id' => $user->id, 'dedup_key' => $key, 'status' => 'pending'] + array_intersect_key($data, array_flip(['subject_type', 'subject_id', 'scheduled_at'])));
                if ($reminder->status === 'cancelled' && $reminder->scheduled_at->isFuture()) {
                    $reminder->update(['status' => 'pending']);
                }
                if ($reminder->status !== 'pending' || $reminder->scheduled_at->isFuture()) {
                    continue;
                }
                if ($reminder->scheduled_at->lt(now()->subDay())) {
                    $reminder->update(['status' => 'expired']);

                    continue;
                }
                $notification = FeedNotification::forUser($user)->firstOrCreate(['dedup_key' => 'reminder:'.$key], [
                    'user_id' => $user->id, 'type' => 'reminder', 'title' => $data['subject_type'] === 'task' ? 'Task reminder' : 'Calendar reminder',
                    'body' => $data['title'], 'target_url' => $data['target_url'],
                ]);
                $reminder->update(['status' => 'delivered', 'notification_id' => $notification->id]);
                foreach ($subscriptions as $subscription) {
                    PushDelivery::firstOrCreate(['notification_id' => $notification->id, 'push_subscription_id' => $subscription->id], ['user_id' => $user->id, 'available_at' => now()]);
                }
            }
        });
        $this->dispatch($user);
    }

    public function dispatch(User $user): void
    {
        if (! app(BrowserPush::class)->configured() || config('queue.connections.'.config('chart.push.connection').'.driver') === 'sync') {
            return;
        }
        PushDelivery::forUser($user)->where('status', 'pending')->where('available_at', '<=', now())->each(function ($delivery) {
            try {
                Bus::dispatch((new SendBrowserPush($delivery->user_id, $delivery->id))->onConnection(config('chart.push.connection'))->onQueue(config('chart.push.queue')));
            } catch (Throwable) {
                // The durable delivery remains pending for the next scheduler run.
            }
        });
    }
}
