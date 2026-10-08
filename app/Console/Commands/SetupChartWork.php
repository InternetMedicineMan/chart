<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\WorkSetup;
use Illuminate\Console\Command;

class SetupChartWork extends Command
{
    protected $signature = 'chart:setup';

    protected $description = 'Initialize the owner Inbox, starter domains and timezone without replacing existing work';

    public function handle(WorkSetup $setup): int
    {
        $user = User::find(config('chart.owner_id'));
        if (! $user) {
            $this->error('Configure CHART_OWNER_ID for an existing account before setting up work records.');

            return self::FAILURE;
        }
        $setup->initialize($user);
        $this->info('Inbox, starter domains and timezone are ready. Existing records were preserved.');

        return self::SUCCESS;
    }
}
