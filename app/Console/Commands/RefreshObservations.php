<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BriefingObservations;
use Illuminate\Console\Command;

class RefreshObservations extends Command
{
    protected $signature = 'observations:refresh {--scheduled : Run once per local day at or after 02:00}';

    protected $description = 'Refresh rule-based Briefing observations for the configured owner';

    public function handle(BriefingObservations $observations): int
    {
        $owner = User::find(config('chart.owner_id'));
        if (! $owner) {
            $this->error('Configure a valid Chart owner first.');

            return self::FAILURE;
        }
        $observations->refresh($owner, true, $this->option('scheduled'));
        $this->info('Observations checked.');

        return self::SUCCESS;
    }
}
