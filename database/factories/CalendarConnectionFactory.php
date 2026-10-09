<?php

namespace Database\Factories;

use App\Models\CalendarConnection;
use App\Models\User;
use App\Services\GoogleCalendarClient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarConnection>
 */
class CalendarConnectionFactory extends Factory
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
            'google_subject' => fake()->unique()->numerify('##########'),
            'email' => fake()->safeEmail(),
            'access_token' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'expires_at' => now()->addHour(),
            'scopes' => GoogleCalendarClient::SCOPES,
            'status' => 'connected',
            'revision' => 0,
        ];
    }
}
