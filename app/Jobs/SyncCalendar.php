<?php

namespace App\Jobs;

use App\Services\CalendarSync;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncCalendar implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 240;

    public int $uniqueFor = 300;

    public function __construct(public int $userId, public int $calendarId) {}

    public function uniqueId(): string
    {
        return $this->userId.':'.$this->calendarId;
    }

    public function handle(CalendarSync $sync): void
    {
        $sync->sync($this->userId, $this->calendarId);
    }
}
