<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class ProvisionChartOwner extends Command
{
    protected $signature = 'chart:owner {email : The owner email address} {--name= : Name for a new account} {--create-locked : Create with an unknown random password for later setup} {--set-password : Set the password interactively}';

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
        if (config('chart.owner_id') && (! $user || (string) $user->id !== (string) config('chart.owner_id'))) {
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
            $this->info('Password set. Complete two-factor setup after signing in.');
        } elseif (! $createdLocked) {
            $this->info('Existing account preserved, including its password and two-factor settings.');
        }

        $this->line('Set CHART_OWNER_ID='.$user->id.' in your environment, then run php artisan config:clear.');

        return self::SUCCESS;
    }
}
