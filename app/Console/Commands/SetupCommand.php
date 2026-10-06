<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;

class SetupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'larafast:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Installs Laravel Jetstream With Configurations';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $stack = select(
            label: 'Choose your frontend stack',
            options: ['inertia' => 'Inertia.js', 'livewire' => 'Livewire', null => 'Blade'],
            default: 'inertia',
        );

        if ($stack === 'inertia') {
            $ssr = confirm(
                label: 'Do you want to enable "SSR" server with Inertia.js?',
                default: 0,
            );
        }

        $verification = confirm(
            label: 'Do you want to install Email Verification support?',
            default: 0,
            hint: 'Requires email client setup. Can be enabled later from config/jetstream.php file.'
        );

        $this->call('jetstream:install', [
            'stack' => $stack,
            '--ssr' => $ssr ?? false,
            '--verification' => $verification,
        ]);

        info('LaraFast Installed Successfully');
        info('Now run:');
        info('npm install');
        info('npm run build');
        info('php artisan migrate');
    }
}
