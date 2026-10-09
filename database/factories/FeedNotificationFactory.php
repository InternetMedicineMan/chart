<?php

namespace Database\Factories;

use App\Models\FeedNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeedNotification>
 */
class FeedNotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'dedup_key' => fake()->uuid(),
            'type' => 'capture_review',
            'title' => 'Capture needs review',
            'body' => 'Open your saved capture to decide where it belongs.',
            'status' => 'unread',
        ];
    }
}
