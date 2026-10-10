<?php

namespace App\Jobs;

use App\Services\CalendarWriting;
use App\Services\CaptureCalendarActions;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ApplyCalendarMutation implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 90;

    public int $uniqueFor = 120;

    public function __construct(public int $userId, public int $mutationId) {}

    public function uniqueId(): string
    {
        return $this->userId.':'.$this->mutationId;
    }

    public function handle(CalendarWriting $writing): void
    {
        $writing->apply($this->userId, $this->mutationId);
        app(CaptureCalendarActions::class)->reconcile($this->userId);
    }
}
