<?php

namespace App\Jobs;

use App\Services\CaptureProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ParseCapture implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 70;

    public function __construct(public int $captureId, public int $userId) {}

    public function handle(CaptureProcessor $processor): void
    {
        $processor->process($this->captureId, $this->userId);
    }
}
