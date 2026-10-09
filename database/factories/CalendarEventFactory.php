<?php

namespace Database\Factories;

use App\Models\CalendarEvent;
use App\Models\ConnectedCalendar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarEvent>
 */
class CalendarEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $googleId = str_replace('-', '', fake()->uuid());

        return [
            'connected_calendar_id' => ConnectedCalendar::factory(),
            'user_id' => fn (array $attributes) => ConnectedCalendar::findOrFail($attributes['connected_calendar_id'])->user_id,
            'google_id' => $googleId,
            'google_key' => hash('sha256', $googleId),
            'etag' => '"version-one"',
            'title' => 'Planning',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'remote_payload' => ['id' => $googleId, 'etag' => '"version-one"', 'summary' => 'Planning', 'start' => ['dateTime' => now()->addDay()->toRfc3339String()], 'end' => ['dateTime' => now()->addDay()->addHour()->toRfc3339String()]],
            'revision' => 1,
        ];
    }
}
