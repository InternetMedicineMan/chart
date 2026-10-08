<?php

namespace App\Services;

use App\Models\Capture;

class CaptureWaiter
{
    public function wait(Capture $capture, float $deadline): Capture
    {
        while (in_array($capture->status, ['received', 'processing', 'parsed'], true) && $this->time() < $deadline) {
            $this->pause((int) min(200000, max(0, ($deadline - $this->time()) * 1000000)));
            $capture->refresh();
        }

        return $capture;
    }

    public function time(): float
    {
        return hrtime(true) / 1000000000;
    }

    protected function pause(int $microseconds): void
    {
        usleep($microseconds);
    }
}
