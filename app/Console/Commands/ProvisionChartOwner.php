<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class ProvisionChartOwner extends Command
{
    protected $signature = 'chart:owner {email : The owner email address} {--name= : Name for a new account} {--create-locked : Create with an unknown random password for later setup} {--set-password : Set the password interactively} {--check : Check owner configuration without changing the account}';

    protected $description = 'Provision or identify the Chart owner without changing an existing password';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        $validator = Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255']]);
        if ($validator->fails()) {
            $this->error('Provide a valid email address.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();
        if ($this->option('check')) {
            return $this->reportReadiness($user) ? self::SUCCESS : self::FAILURE;
        }

        if (config('chart.owner_id') && (! $user || (string) $user->id !== (string) config('chart.owner_id'))) {
            $this->reportReadiness($user);
            $this->error('Chart already has a configured owner. Change the owner configuration explicitly to transfer access.');

            return self::FAILURE;
        }

        $createdLocked = false;
        if (! $user && $this->option('create-locked')) {
            if (! $this->option('name') || mb_strlen($this->option('name')) > 255) {
                $this->error('Provide --name with 1 to 255 characters when creating a locked owner.');

                return self::FAILURE;
            }
            $createdLocked = true;
            $user = User::create([
                'name' => $this->option('name') ?: $email,
                'email' => $email,
                'password' => Str::random(64),
                'email_verified_at' => now(),
            ]);
            $this->info('Owner created with an unknown password. Run this command with --set-password before signing in.');
        }

        if (! $user || $this->option('set-password')) {
            $name = $user?->name ?: ($this->option('name') ?: $this->ask('Owner name'));
            $password = $this->secret('New password (at least 12 characters)');
            $confirmation = $this->secret('Confirm password');
            $validator = Validator::make([
                'name' => $name,
                'password' => $password,
                'password_confirmation' => $confirmation,
            ], [
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'confirmed', Password::min(12)],
            ]);
            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $error) {
                    $this->error($error);
                }

                return self::FAILURE;
            }

            if ($user) {
                $user->forceFill(['password' => $password, 'remember_token' => null])->save();
            } else {
                $user = User::create(['name' => $name, 'email' => $email, 'password' => $password, 'email_verified_at' => now()]);
            }
            $this->info('Password saved in this environment.');
        } elseif (! $createdLocked) {
            $this->info('Existing account preserved, including its password and two-factor settings.');
        }

        $this->reportReadiness($user);

        return self::SUCCESS;
    }

    private function reportReadiness(?User $user): bool
    {
        $ownerId = config('chart.owner_id');
        $this->line('Environment: '.app()->environment().' ('.config('app.url').')');
        $this->line('Configured CHART_OWNER_ID: '.($ownerId ?: '(not set)'));

        if (! $user) {
            $this->error('This email has no account in this environment. Local and deployed databases are separate.');

            return false;
        }

        $this->line('Account ID in this database: '.$user->id);
        if (! $ownerId || (string) $user->id !== (string) $ownerId) {
            $this->error('Sign-in is blocked by the owner configuration, regardless of the password.');
            $this->line('Set CHART_OWNER_ID='.$user->id.' in this server\'s environment, then run php artisan config:clear.');
            $this->line('If deployment caches configuration, rebuild it with php artisan config:cache.');

            return false;
        }

        $this->info('Owner configuration matches this account.');
        if (! $user->hasEnabledTwoFactorAuthentication()) {
            $this->line('After signing in, enable two-factor authentication and confirm the code on Settings to unlock Chart.');
        }

        return true;
    }
}
