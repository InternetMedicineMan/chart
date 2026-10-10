<?php

namespace Database\Factories;

use App\Models\Reminder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reminder>
 */
class ReminderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'dedup_key' => hash('sha256', fake()->uuid()), 'subject_type' => 'task', 'subject_id' => 1, 'scheduled_at' => now()->addHour()];
    }
}
