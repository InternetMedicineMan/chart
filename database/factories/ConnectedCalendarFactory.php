<?php

namespace Database\Factories;

use App\Models\CalendarConnection;
use App\Models\ConnectedCalendar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConnectedCalendar>
 */
class ConnectedCalendarFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $googleId = fake()->unique()->safeEmail();

        return [
            'calendar_connection_id' => CalendarConnection::factory(),
            'user_id' => fn (array $attributes) => CalendarConnection::findOrFail($attributes['calendar_connection_id'])->user_id,
            'google_id' => $googleId,
            'google_key' => hash('sha256', $googleId),
            'name' => 'Personal',
            'timezone' => 'America/Chicago',
            'access_role' => 'owner',
            'mode' => 'off',
            'revision' => 0,
        ];
    }
}
