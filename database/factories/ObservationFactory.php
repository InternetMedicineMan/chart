<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ObservationFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'rule_type' => 'task_due', 'subject_type' => 'task', 'subject_id' => 1, 'title' => 'Review draft', 'body' => 'Due today', 'data' => ['url' => '/bench'], 'score' => 105, 'urgency' => 'high', 'dedup_key' => Str::uuid()->toString(), 'observed_on' => now()->toDateString(), 'expires_at' => now()->addDays(60)];
    }
}
