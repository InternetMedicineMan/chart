<?php

namespace App\Console\Commands;

use App\Exceptions\CalendarFailure;
use App\Jobs\SyncCalendar;
use App\Models\CalendarConnection;
use App\Models\CalendarMutation;
use App\Models\ConnectedCalendar;
use App\Services\CalendarSync;
use App\Services\CalendarWriting;
use Illuminate\Console\Command;

class SyncCalendars extends Command
{
    protected $signature = 'calendar:sync {--writes-only : Redispatch only saved outgoing changes}';

    protected $description = 'Queue selected calendars for sync and recover saved outgoing changes';

    public function handle(CalendarSync $sync, CalendarWriting $writing): int
    {
        if (config('queue.connections.'.config('chart.calendar.queue_connection').'.driver') === 'sync') {
            $this->error('Calendar requires an asynchronous queue.');

            return self::FAILURE;
        }
        $userId = (int) config('chart.owner_id');
        $connection = CalendarConnection::forUser($userId)->where('status', 'connected')->first();
        if (! $connection) {
            return self::SUCCESS;
        }
        if (! $this->option('writes-only')) {
            try {
                $sync->discover($connection);
            } catch (CalendarFailure $exception) {
                $connection->update(['error' => $exception->getMessage()]);
                $this->warn($exception->getMessage());

                return self::FAILURE;
            }
            ConnectedCalendar::forUser($userId)->where('calendar_connection_id', $connection->id)->where('mode', '!=', 'off')->each(function ($calendar) {
                SyncCalendar::dispatch($calendar->user_id, $calendar->id)->onConnection(config('chart.calendar.queue_connection'))->onQueue(config('chart.calendar.queue'));
            });
        }
        CalendarMutation::forUser($userId)->where('status', 'pending')->where('available_at', '<=', now())->each(fn ($mutation) => $writing->dispatch($mutation));
        $this->info('Calendar work queued.');

        return self::SUCCESS;
    }
}
