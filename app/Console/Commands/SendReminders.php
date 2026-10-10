<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ReminderDelivery;
use Illuminate\Console\Command;

class SendReminders extends Command
{
    protected $signature = 'reminders:send';

    protected $description = 'Save due reminders and recover pending browser notifications';

    public function handle(ReminderDelivery $reminders): int
    {
        $user = User::find(config('chart.owner_id'));
        if ($user) {
            $reminders->run($user);
        }
        $this->info('Reminder check complete.');

        return self::SUCCESS;
    }
}
