<?php

namespace App\Console\Commands;

use App\Models\Capture;
use App\Services\CaptureService;
use Illuminate\Console\Command;

class RecoverCaptures extends Command
{
    protected $signature = 'capture:recover';

    protected $description = 'Redispatch saved captures whose processing is pending or interrupted';

    public function handle(CaptureService $service): int
    {
        if (! $service->enabled()) {
            $this->info('Automatic sorting is disabled or has no API key. Captures remain saved.');

            return self::SUCCESS;
        }
        $count = 0;
        Capture::forUser((int) config('chart.owner_id'))
            ->whereIn('status', ['received', 'processing', 'parsed', 'failed'])
            ->where(fn ($q) => $q->whereNull('lease_until')->orWhere('lease_until', '<=', now()))
            ->where(fn ($q) => $q->whereNull('available_at')->orWhere('available_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('dispatched_at')->orWhere('dispatched_at', '<=', now()->subMinutes(2)))
            ->where(fn ($q) => $q->where('attempts', '<', 3)->orWhereNotNull('parsed')->orWhere('status', 'processing'))
            ->chunkById(100, function ($captures) use ($service, &$count) {
                foreach ($captures as $capture) {
                    $service->dispatch($capture);
                    $count++;
                }
            });
        $this->info("Queued {$count} saved captures.");

        return self::SUCCESS;
    }
}
