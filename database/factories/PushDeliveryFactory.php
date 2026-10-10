<?php

namespace Database\Factories;

use App\Models\FeedNotification;
use App\Models\PushDelivery;
use App\Models\PushSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PushDelivery>
 */
class PushDeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['push_subscription_id' => PushSubscription::factory(), 'user_id' => fn (array $attributes) => PushSubscription::findOrFail($attributes['push_subscription_id'])->user_id, 'notification_id' => fn (array $attributes) => FeedNotification::factory()->create(['user_id' => $attributes['user_id']])->id, 'available_at' => now()];
    }
}
