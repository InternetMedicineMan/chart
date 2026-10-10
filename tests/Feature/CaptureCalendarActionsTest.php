<?php

use App\Models\AppSetting;
use App\Models\CalendarConnection;
use App\Models\CalendarEvent;
use App\Models\CalendarMutation;
use App\Models\Capture;
use App\Models\CaptureItem;
use App\Models\ConnectedCalendar;
use App\Models\FeedNotification;
use App\Models\User;
use App\Services\CaptureActions;
use App\Services\CaptureCalendarActions;
use App\Services\CaptureService;
use App\Services\WorkSetup;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09T12:00:00Z'));
    $this->owner = User::factory()->create(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()]);
    config(['chart.owner_id' => $this->owner->id, 'inertia.ssr.enabled' => false]);
    $this->actingAs($this->owner);
    app(WorkSetup::class)->initialize($this->owner);
    AppSetting::forUser($this->owner)->updateOrCreate(['key' => 'timezone'], ['user_id' => $this->owner->id, 'value' => 'America/Chicago']);
    Bus::fake();
    Http::preventStrayRequests();
    $connection = CalendarConnection::factory()->create(['user_id' => $this->owner->id]);
    $this->calendar = ConnectedCalendar::factory()->create(['user_id' => $this->owner->id, 'calendar_connection_id' => $connection->id, 'name' => 'Personal', 'mode' => 'two_way']);
    $this->item = function (array $overrides = []) {
        $capture = Capture::create(['request_key' => Str::uuid()->toString(), 'source' => 'in_app', 'user_id' => $this->owner->id, 'raw_text' => 'Schedule lunch tomorrow at noon', 'client_captured_at' => now(), 'timezone' => 'America/Chicago', 'parsed' => true]);

        return CaptureItem::create(['user_id' => $this->owner->id, 'capture_id' => $capture->id, 'sequence' => 0, 'action_type' => 'create_event', 'excerpt' => $capture->raw_text, 'confidence' => .95, 'status' => 'pending',
            'payload' => $overrides + ['type' => 'create_event', 'confidence' => .95, 'excerpt' => $capture->raw_text, 'title' => 'Lunch', 'event_start' => '2026-10-10T12:00']]);
    };
});

it('queues an event once with a sixty minute default and an honest confirmation', function () {
    $item = ($this->item)();
    app(CaptureActions::class)->execute($item);
    app(CaptureActions::class)->execute($item);
    $mutation = CalendarMutation::sole();
    expect($item->fresh()->status)->toBe('executed')->and($mutation->payload['end']['dateTime'])->toBe('2026-10-10T13:00:00-05:00');
    expect(FeedNotification::sole()->title)->toBe('Calendar event queued');
    expect(app(CaptureService::class)->confirmation(Capture::find($item->capture_id))['spoken_confirmation'])->toContain('calendar requests saved');
    Http::assertNothingSent();
    $this->get(route('captures.show', $item->capture_id))->assertOk();
    app(CaptureActions::class)->undo($item);
    expect($mutation->fresh()->status)->toBe('discarded')->and($item->fresh()->status)->toBe('undone');
});

it('requires review for unsafe time or confidence', function (array $payload) {
    $item = ($this->item)($payload);
    app(CaptureActions::class)->execute($item);
    expect($item->fresh()->status)->toBe('needs_triage');
    expect(CalendarMutation::count())->toBe(0);
})->with([
    [['event_start' => '2026-10-08T12:00']],
    [['event_start' => '2027-03-14T02:30']],
    [['event_start' => '2026-11-01T01:30']],
    [['event_end' => '2026-10-10T11:00']],
    [['confidence' => .7]],
    [['calendar_ref' => 'Missing']],
]);

it('does not guess between calendars or write to a read-only calendar', function () {
    $this->calendar->update(['is_primary' => false]);
    ConnectedCalendar::factory()->create(['user_id' => $this->owner->id, 'calendar_connection_id' => $this->calendar->calendar_connection_id, 'mode' => 'two_way', 'is_primary' => false, 'name' => 'Work']);
    $item = ($this->item)();
    app(CaptureActions::class)->execute($item);
    expect($item->fresh()->status)->toBe('needs_triage');
    $this->calendar->update(['mode' => 'read_only']);
    $item = ($this->item)(['calendar_ref' => 'Personal']);
    app(CaptureActions::class)->execute($item);
    expect($item->fresh()->status)->toBe('needs_triage');
});

it('requires reviewed calendar revision and current timezone', function () {
    $item = ($this->item)();
    Capture::whereKey($item->capture_id)->update(['timezone' => 'America/New_York']);
    app(CaptureActions::class)->execute($item);
    expect($item->fresh()->status)->toBe('needs_triage');
    $this->put(route('capture-items.resolve', $item->id), ['type' => 'create_event', 'title' => 'Lunch', 'event_start' => '2026-10-10T12:00', 'calendar_id' => $this->calendar->id, 'calendar_revision' => $this->calendar->revision])->assertSessionHasNoErrors();
    expect($item->fresh()->status)->toBe('executed');
});

it('keeps async undo pending until Google confirms and reconciles the feed', function () {
    $item = ($this->item)();
    app(CaptureActions::class)->execute($item);
    $mutation = CalendarMutation::sole();
    $mutation->update(['status' => 'applied', 'applied_at' => now(), 'applied_etag' => 'v1']);
    CalendarEvent::whereKey($mutation->calendar_event_id)->update(['etag' => 'v1']);
    app(CaptureActions::class)->undo($item);
    expect($item->fresh()->status)->toBe('executed');
    app(CaptureCalendarActions::class)->reconcile($this->owner->id);
    expect(FeedNotification::where('action_log_id', '!=', null)->first()->title)->toBe('Calendar undo queued');
    CalendarMutation::where('undo_of_id', $mutation->id)->update(['status' => 'applied', 'applied_at' => now()]);
    app(CaptureCalendarActions::class)->reconcile($this->owner->id);
    expect($item->fresh()->status)->toBe('undone');
});
