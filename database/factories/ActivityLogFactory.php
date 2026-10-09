<?php

namespace Database\Factories;

use App\Models\Domain;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ActivityLogFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'subject_type' => 'domain',
            'domain_id' => fn (array $attributes) => Domain::create(['user_id' => $attributes['user_id'], 'name' => 'Writing', 'slug' => Str::uuid()->toString()])->id,
            'subject_id' => fn (array $attributes) => $attributes['domain_id'],
            'entry' => fake()->sentence(), 'minutes' => 30, 'occurred_at' => now(), 'request_key' => Str::uuid()->toString()];
    }
}
