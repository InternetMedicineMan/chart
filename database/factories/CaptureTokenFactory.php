<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CaptureTokenFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(), 'label' => 'Test iPhone', 'device_name' => 'iPhone',
            'token_hash' => hash('sha256', Str::random(64)), 'scopes' => ['capture:write'], 'rate_limit_per_hour' => 120,
        ];
    }
}
