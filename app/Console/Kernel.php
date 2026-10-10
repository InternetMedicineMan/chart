<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('observations:refresh --scheduled')->everyTenMinutes()->withoutOverlapping();
        $schedule->command('calendar:sync')->everyFifteenMinutes()->withoutOverlapping();
        $schedule->command('calendar:sync --writes-only')->everyMinute()->withoutOverlapping();
        $schedule->command('reminders:send')->everyMinute()->withoutOverlapping();
        $schedule->command('capture:recover')->everyMinute()->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
