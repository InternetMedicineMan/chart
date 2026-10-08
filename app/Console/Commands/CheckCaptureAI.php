<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckCaptureAI extends Command
{
    protected $signature = 'chart:ai-check';

    protected $description = 'Check local capture AI configuration without displaying secrets or making API calls';

    public function handle(): int
    {
        $key = filled(config('chart.capture.key'));
        $this->table(['Setting', 'Value'], [
            ['API key', $key ? 'Configured (not verified)' : 'Missing'],
            ['Model', config('chart.capture.model')],
            ['Automatic sorting', config('chart.capture.enabled') ? 'Enabled' : 'Disabled'],
            ['Queue connection', config('chart.capture.connection')],
            ['Config cache', app()->configurationIsCached() ? 'Cached; rebuild after environment changes' : 'Not cached'],
        ]);
        $this->info('This checks configuration only. It does not verify billing, model access, workers, or scheduler health.');
        $this->line('Controlled live test: php artisan parser:eval --case=inbox_task --live');

        return $key ? self::SUCCESS : self::FAILURE;
    }
}
