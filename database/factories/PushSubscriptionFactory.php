<?php

namespace Database\Factories;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Minishlink\WebPush\VAPID;

/**
 * @extends Factory<PushSubscription>
 */
class PushSubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $endpoint = 'https://fcm.googleapis.com/fcm/send/'.fake()->uuid();

        return ['user_id' => User::factory(), 'endpoint_hash' => hash('sha256', $endpoint), 'subscription' => ['endpoint' => $endpoint, 'keys' => ['p256dh' => VAPID::createVapidKeys()['publicKey'], 'auth' => rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=')]], 'label' => 'Test browser'];
    }
}
