<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GeneratePushKeys extends Command
{
    protected $signature = 'chart:push-keys';

    protected $description = 'Generate VAPID keys to save privately in the deployment environment';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();
        $this->warn('Save both values privately in Forge. Keep existing keys once browsers subscribe.');
        $this->line('CHART_PUSH_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('CHART_PUSH_PRIVATE_KEY='.$keys['privateKey']);

        return self::SUCCESS;
    }
}
