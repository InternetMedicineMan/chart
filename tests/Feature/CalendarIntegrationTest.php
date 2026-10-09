<?php

use App\Jobs\ApplyCalendarMutation;
use App\Models\AppSetting;
use App\Models\CalendarConnection;
use App\Models\CalendarEvent;
use App\Models\CalendarMutation;
use App\Models\ConnectedCalendar;
use App\Models\Observation;
use App\Models\User;
use App\Services\BriefingObservations;
use App\Services\CalendarBriefing;
use App\Services\CalendarSync;
use App\Services\CalendarWriting;
use App\Services\GoogleCalendarClient;
use App\Services\WorkSetup;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09T12:00:00Z'));
    $this->owner = User::factory()->create(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()]);
    config(['chart.owner_id' => $this->owner->id, 'inertia.ssr.enabled' => false, 'chart.calendar.client_id' => 'test-client', 'chart.calendar.client_secret' => 'test-secret']);
    $this->actingAs($this->owner);
    app(WorkSetup::class)->initialize($this->owner);
    AppSetting::forUser($this->owner)->updateOrCreate(['key' => 'timezone'], ['user_id' => $this->owner->id, 'value' => 'America/Chicago']);
    Http::preventStrayRequests();
    Bus::fake();
    $this->connection = CalendarConnection::factory()->create(['user_id' => $this->owner->id]);
    $this->calendar = ConnectedCalendar::factory()->create(['user_id' => $this->owner->id, 'calendar_connection_id' => $this->connection->id, 'mode' => 'two_way', 'last_synced_at' => now()]);
    $this->sync = app(CalendarSync::class);
    $this->writing = app(CalendarWriting::class);
    $this->remote = fn (array $overrides = []) => array_replace_recursive(['id' => 'event1', 'etag' => '"v1"', 'summary' => 'Planning', 'start' => ['dateTime' => '2026-10-10T09:00:00-05:00'], 'end' => ['dateTime' => '2026-10-10T10:00:00-05:00']], $overrides);
    $this->data = fn (array $overrides = []) => $overrides + ['request_key' => Str::uuid()->toString(), 'calendar_id' => $this->calendar->id, 'title' => 'Updated planning', 'all_day' => false, 'starts_at' => '2026-10-10T09:00', 'ends_at' => '2026-10-10T10:00'];
});

it('encrypts tokens and never exposes tokens or raw event payloads in pages', function () {
    $this->sync->store($this->calendar, ($this->remote)());
    expect(DB::table('calendar_connections')->value('access_token'))->not->toBe('test-access-token');
    expect($this->connection->fresh()->access_token)->toBe('test-access-token');
    $this->get(route('calendar.settings'))->assertOk()->assertInertia(fn (Assert $p) => $p->missing('connection.access_token')->missing('connection.refresh_token')->missing('calendars.0.sync_token'));
    $this->get(route('calendar.index'))->assertOk()->assertInertia(fn (Assert $p) => $p->has('events.data', 1)->missing('events.data.0.remote_payload'));
    Http::assertNothingSent();
});

it('uses owner-bound expiring single-use OAuth state', function () {
    $response = $this->post(route('calendar.connect'));
    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
    expect($query)->toMatchArray(['access_type' => 'offline', 'prompt' => 'consent select_account']);
    $this->get(route('calendar.callback', ['state' => 'wrong', 'code' => 'code']))->assertForbidden();
    $this->get(route('calendar.callback', ['state' => $query['state'], 'code' => 'code']))->assertForbidden();
    Http::assertNothingSent();
});

it('connects with scoped offline access and leaves newly discovered calendars off', function () {
    $this->calendar->delete();
    $this->connection->delete();
    $response = $this->post(route('calendar.connect'));
    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'new-access', 'refresh_token' => 'new-refresh', 'scope' => implode(' ', GoogleCalendarClient::SCOPES)]),
        'openidconnect.googleapis.com/*' => Http::response(['sub' => 'google-sub', 'email' => 'owner@example.test', 'email_verified' => true]),
        'www.googleapis.com/calendar/v3/users/me/calendarList*' => Http::response(['items' => [['id' => 'primary@example.test', 'summary' => 'Work', 'accessRole' => 'owner']]]),
    ]);
    $this->get(route('calendar.callback', ['state' => $query['state'], 'code' => 'code']))->assertRedirect(route('calendar.settings'))->assertSessionHasNoErrors();
    expect(CalendarConnection::first()->refresh_token)->toBe('new-refresh')->and(ConnectedCalendar::first()->mode)->toBe('off');
    $this->get(route('calendar.callback', ['state' => $query['state'], 'code' => 'code']))->assertForbidden();
});

it('rejects missing scopes without replacing stored credentials', function () {
    $response = $this->post(route('calendar.connect'));
    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'bad', 'scope' => 'openid email']), 'openidconnect.googleapis.com/*' => Http::response(['sub' => $this->connection->google_subject, 'email' => $this->connection->email, 'email_verified' => true])]);
    $this->get(route('calendar.callback', ['state' => $query['state'], 'code' => 'code']))->assertRedirect(route('calendar.settings'))->assertSessionHasErrors('calendar');
    expect($this->connection->fresh()->access_token)->toBe('test-access-token');
});

it('refreshes expired access tokens and retains the offline token', function () {
    $this->connection->update(['expires_at' => now()->subMinute()]);
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'renewed', 'expires_in' => 3600]), 'www.googleapis.com/*' => Http::response(['items' => []])]);
    app(GoogleCalendarClient::class)->request($this->connection, 'GET', 'users/me/calendarList');
    expect($this->connection->fresh()->access_token)->toBe('renewed')->and($this->connection->fresh()->refresh_token)->toBe('test-refresh-token');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'calendarList') && $r->hasHeader('Authorization', 'Bearer renewed'));
});

it('marks revoked offline access for reconnection without leaking Google error text', function () {
    $this->connection->update(['expires_at' => now()->subMinute()]);
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant', 'error_description' => 'private-token-leak'], 400)]);
    $this->sync->sync($this->owner->id, $this->calendar->id);
    expect($this->connection->fresh()->status)->toBe('reauth_required')->and($this->calendar->fresh()->error)->not->toContain('private-token-leak');
});

it('paginates source and occurrence sync and applies cancellations incrementally', function () {
    $master = ($this->remote)(['id' => 'series', 'recurrence' => ['RRULE:FREQ=DAILY']]);
    $instance = ($this->remote)(['id' => 'instance', 'recurringEventId' => 'series']);
    Http::fakeSequence()->push(['items' => [$master], 'nextPageToken' => 'page2'])->push(['items' => [], 'nextSyncToken' => 'token1'])->push(['items' => [$instance]])->push(['items' => [['id' => 'instance', 'etag' => '"v2"', 'status' => 'cancelled']], 'nextSyncToken' => 'token2'])->push(['items' => []]);
    $this->sync->sync($this->owner->id, $this->calendar->id);
    expect(CalendarEvent::count())->toBe(2)->and($this->calendar->fresh()->sync_token)->toBe('token1');
    $this->get(route('calendar.index'))->assertInertia(fn (Assert $p) => $p->has('events.data', 1)->where('events.data.0.google_id', 'instance'));
    $this->travel(1)->minutes();
    $this->sync->sync($this->owner->id, $this->calendar->id);
    expect(CalendarEvent::where('google_id', 'instance')->first()->status)->toBe('cancelled')->and($this->calendar->fresh()->sync_token)->toBe('token2');
    Http::assertSent(fn ($r) => ($r['syncToken'] ?? null) === 'token1' && ($r['singleEvents'] ?? null) === 'false');
});

it('resets expired sync tokens and removes missing copies only after a complete result', function () {
    $event = $this->sync->store($this->calendar, ($this->remote)());
    $this->calendar->update(['sync_token' => 'expired']);
    $this->travel(1)->minutes();
    Http::fakeSequence()->push([], 410)->push(['items' => [], 'nextSyncToken' => 'fresh'])->push(['items' => []]);
    $this->sync->sync($this->owner->id, $this->calendar->id);
    expect($event->fresh()->removed_at)->not->toBeNull()->and($this->calendar->fresh()->sync_token)->toBe('fresh');
});

it('does not advance a token or replace data when a later sync page fails', function () {
    $event = $this->sync->store($this->calendar, ($this->remote)());
    $this->calendar->update(['sync_token' => 'old']);
    Http::fakeSequence()->push(['items' => [], 'nextPageToken' => 'next'])->push([], 503);
    $this->sync->sync($this->owner->id, $this->calendar->id);
    expect($event->fresh()->removed_at)->toBeNull()->and($this->calendar->fresh()->sync_token)->toBe('old')->and($this->calendar->fresh()->error)->not->toBeNull();
});

it('requires explicit write selection and enforces ownership', function () {
    $this->calendar->update(['mode' => 'read_only']);
    $this->post(route('calendar.events.store'), ($this->data)())->assertSessionHasErrors('calendar');
    $other = ConnectedCalendar::factory()->create(['mode' => 'two_way']);
    $this->post(route('calendar.events.store'), ($this->data)(['calendar_id' => $other->id]))->assertSessionHasErrors('calendar_id');
    $this->put(route('calendar.selection', $other), ['mode' => 'two_way', 'revision' => 0])->assertNotFound();
    expect(CalendarMutation::count())->toBe(0);
    Http::assertNothingSent();
});

it('saves an idempotent queued create without sending any HTTP in the request', function () {
    $data = ($this->data)();
    $this->post(route('calendar.events.store'), $data)->assertSessionHasNoErrors();
    $this->post(route('calendar.events.store'), $data)->assertSessionHasNoErrors();
    $this->post(route('calendar.events.store'), array_replace($data, ['title' => 'Different']))->assertSessionHasErrors('request_key');
    expect(CalendarMutation::count())->toBe(1)->and(CalendarEvent::count())->toBe(1)->and(CalendarMutation::first()->status)->toBe('pending');
    Bus::assertDispatched(ApplyCalendarMutation::class);
    Http::assertNothingSent();
});

it('creates once with a stable Google ID and confirms uncertain successful responses', function () {
    $mutation = $this->writing->save($this->owner, ($this->data)());
    $event = CalendarEvent::first();
    $remote = $mutation->payload + ['id' => $event->google_id, 'etag' => '"created"', 'extendedProperties' => ['private' => ['chartMutation' => $mutation->request_key]]];
    Http::fakeSequence()->push([], 404)->push([], 503)->push($remote);
    $this->writing->apply($this->owner->id, $mutation->id);
    expect($mutation->fresh()->status)->toBe('pending')->and($mutation->fresh()->attempts)->toBe(1);
    $this->post(route('calendar.changes.discard', $mutation))->assertSessionHasErrors('mutation');
    $this->travel(3)->minutes();
    $this->writing->apply($this->owner->id, $mutation->id);
    $this->writing->apply($this->owner->id, $mutation->id);
    expect($mutation->fresh()->status)->toBe('applied')->and($event->fresh()->etag)->toBe('"created"');
    Http::assertSentCount(3);
    Http::assertSent(fn ($r) => $r->method() === 'POST' && $r['id'] === $event->google_id);
});

it('uses conditional updates and keeps the newest Google version after a racing edit', function () {
    $remote = ($this->remote)();
    $event = $this->sync->store($this->calendar, $remote);
    $mutation = $this->writing->save($this->owner, ($this->data)(['revision' => $event->revision]), $event->id);
    Http::fakeSequence()->push($remote)->push([], 412)->push(($this->remote)(['etag' => '"v3"', 'summary' => 'Newer Google edit']));
    $this->writing->apply($this->owner->id, $mutation->id);
    expect($mutation->fresh()->status)->toBe('conflict')->and($event->fresh()->title)->toBe('Newer Google edit');
    Http::assertSent(fn ($r) => $r->method() === 'PATCH' && $r->hasHeader('If-Match', '"v1"'));
    $this->writing->discard($this->owner, $mutation->id);
    expect($event->fresh()->title)->toBe('Newer Google edit');
});

it('confirms an update and queues a reversible edit with the original event times', function () {
    $remote = ($this->remote)();
    $event = $this->sync->store($this->calendar, $remote);
    $mutation = $this->writing->save($this->owner, ($this->data)(['revision' => $event->revision]), $event->id);
    $updated = ($this->remote)(['etag' => '"v2"', 'summary' => 'Updated planning']);
    Http::fakeSequence()->push($remote)->push($updated)->push($updated)->push($remote + []);
    $this->writing->apply($this->owner->id, $mutation->id);
    $this->writing->undo($this->owner, $mutation->id);
    $undo = CalendarMutation::where('undo_of_id', $mutation->id)->firstOrFail();
    expect($undo->expected_etag)->toBe('"v2"')->and($undo->payload['summary'])->toBe('Planning');
    $this->writing->apply($this->owner->id, $undo->id);
    expect($undo->fresh()->status)->toBe('applied')->and($event->fresh()->title)->toBe('Planning');
});

it('undoes a created event with a conditional delete and protects later edits', function () {
    $mutation = $this->writing->save($this->owner, ($this->data)());
    $event = CalendarEvent::first();
    $remote = $mutation->payload + ['id' => $event->google_id, 'etag' => '"created"'];
    Http::fakeSequence()->push([], 404)->push($remote)->push($remote)->push([], 204);
    $this->writing->apply($this->owner->id, $mutation->id);
    $this->writing->undo($this->owner, $mutation->id);
    $undo = CalendarMutation::where('undo_of_id', $mutation->id)->firstOrFail();
    $this->writing->apply($this->owner->id, $undo->id);
    expect($event->fresh()->removed_at)->not->toBeNull();
    Http::assertSent(fn ($r) => $r->method() === 'DELETE' && $r->hasHeader('If-Match', '"created"'));
});

it('preserves all-day boundaries and rejects nonexistent or ambiguous local times', function () {
    $payload = $this->writing->payload($this->owner, ($this->data)(['all_day' => true, 'start_date' => '2026-10-10', 'end_date' => '2026-10-12']));
    expect($payload['start']['date'])->toBe('2026-10-10')->and($payload['end']['date'])->toBe('2026-10-13');
    foreach (['2026-03-08T02:30', '2026-11-01T01:30'] as $time) {
        expect(fn () => $this->writing->payload($this->owner, ($this->data)(['starts_at' => $time, 'ends_at' => '2026-11-02T10:00'])))->toThrow(ValidationException::class);
    }
});

it('deduplicates overlapping busy time and suppresses stale load estimates', function () {
    $this->sync->store($this->calendar, ($this->remote)());
    $this->sync->store($this->calendar, ($this->remote)(['id' => 'overlap', 'start' => ['dateTime' => '2026-10-10T09:30:00-05:00'], 'end' => ['dateTime' => '2026-10-10T11:00:00-05:00']]));
    $this->sync->store($this->calendar, ['id' => 'allday', 'etag' => '"day"', 'summary' => 'Holiday', 'start' => ['date' => '2026-10-10'], 'end' => ['date' => '2026-10-11']]);
    $this->sync->store($this->calendar, ($this->remote)(['id' => 'free', 'transparency' => 'transparent', 'end' => ['dateTime' => '2026-10-10T19:00:00-05:00']]));
    expect(app(CalendarBriefing::class)->tomorrow($this->owner))->toMatchArray(['minutes' => 120, 'all_day_count' => 1]);
    $this->calendar->update(['last_synced_at' => now()->subHour()]);
    expect(app(CalendarBriefing::class)->tomorrow($this->owner))->toBeNull();
});

it('blocks disconnecting with pending edits and removes local credentials after discarding', function () {
    $mutation = $this->writing->save($this->owner, ($this->data)());
    $this->delete(route('calendar.disconnect'))->assertSessionHasErrors('calendar');
    $this->writing->discard($this->owner, $mutation->id);
    $this->delete(route('calendar.disconnect'))->assertSessionHasNoErrors();
    expect($this->connection->fresh()->access_token)->toBeNull()->and($this->connection->fresh()->status)->toBe('disconnected')->and($this->calendar->fresh()->mode)->toBe('off');
});

it('downgrades revoked write permissions and never automatically re-enables them', function () {
    Http::fakeSequence()->push(['items' => [['id' => $this->calendar->google_id, 'summary' => 'Personal', 'accessRole' => 'reader']]])->push(['items' => [['id' => $this->calendar->google_id, 'summary' => 'Personal', 'accessRole' => 'owner']]]);
    $this->sync->discover($this->connection);
    expect($this->calendar->fresh()->mode)->toBe('read_only');
    $this->sync->discover($this->connection);
    expect($this->calendar->fresh()->mode)->toBe('read_only');
});

it('does not overwrite outbound edits made while an inbound sync was fetching', function () {
    $event = $this->sync->store($this->calendar, ($this->remote)());
    $this->travel(1)->minutes();
    $this->travelTo(now()->setMicrosecond(500000));
    $calls = 0;
    Http::fake(function () use (&$calls) {
        $calls++;
        if ($calls === 1) {
            $this->sync->store($this->calendar, ($this->remote)(['etag' => '"new"', 'summary' => 'Just edited']));

            return Http::response(['items' => [($this->remote)()], 'nextSyncToken' => 'latest']);
        }

        return Http::response(['items' => [($this->remote)()]]);
    });
    $this->sync->sync($this->owner->id, $this->calendar->id);
    expect($event->fresh()->title)->toBe('Just edited');
});

it('ignores inbound responses if calendar access changes during the request', function () {
    Http::fake(function () {
        $this->calendar->update(['mode' => 'off', 'revision' => 1]);

        return Http::response(['items' => [($this->remote)()], 'nextSyncToken' => 'token']);
    });
    $this->sync->sync($this->owner->id, $this->calendar->id);
    expect(CalendarEvent::count())->toBe(0)->and($this->calendar->fresh()->sync_token)->toBeNull();
});

it('rejects stale form revisions and undo after a later event change', function () {
    $event = $this->sync->store($this->calendar, ($this->remote)());
    $this->put(route('calendar.events.update', $event), ($this->data)(['revision' => 0]))->assertSessionHasErrors('event');
    $mutation = $this->writing->save($this->owner, ($this->data)(['revision' => $event->revision]), $event->id);
    $mutation->update(['status' => 'applied', 'applied_at' => now(), 'applied_etag' => '"previous"']);
    expect(fn () => $this->writing->undo($this->owner, $mutation->id))->toThrow(ValidationException::class);
    Http::assertNothingSent();
});

it('recovers queued writes through the command without making a remote call', function () {
    $mutation = $this->writing->save($this->owner, ($this->data)());
    Bus::fake();
    $this->artisan('calendar:sync --writes-only')->assertSuccessful();
    Bus::assertDispatched(ApplyCalendarMutation::class, fn ($job) => $job->mutationId === $mutation->id);
    Http::assertNothingSent();
});

it('refuses a synchronous queue so saving an event never sends it from a page request', function () {
    config(['chart.calendar.queue_connection' => 'sync']);
    $mutation = $this->writing->save($this->owner, ($this->data)());
    expect($mutation->fresh()->status)->toBe('pending')->and($mutation->fresh()->error)->not->toBeNull();
    Bus::assertNothingDispatched();
    Http::assertNothingSent();
});

it('excludes declined events and sorts Now Next chronologically with a three item cap', function () {
    $this->sync->store($this->calendar, ($this->remote)(['id' => 'declined', 'attendees' => [['self' => true, 'responseStatus' => 'declined']]]));
    foreach (range(1, 4) as $day) {
        $this->sync->store($this->calendar, ['id' => 'day'.$day, 'etag' => '"day"', 'summary' => 'Day '.$day, 'start' => ['date' => '2026-10-'.(10 + $day)], 'end' => ['date' => '2026-10-'.(11 + $day)]]);
    }
    $this->sync->store($this->calendar, ($this->remote)());
    $briefing = app(CalendarBriefing::class)->forUser($this->owner);
    expect($briefing['events'])->toHaveCount(3)->and($briefing['events']->first()->google_id)->toBe('event1');
});

it('hides removed calendars and other owners data from both calendar and briefing', function () {
    $other = ConnectedCalendar::factory()->create(['mode' => 'two_way']);
    $this->sync->store($other, ($this->remote)());
    $this->calendar->update(['mode' => 'off']);
    $this->sync->store($this->calendar, ($this->remote)());
    $this->get(route('calendar.index'))->assertInertia(fn (Assert $p) => $p->has('events.data', 0));
    expect(app(CalendarBriefing::class)->forUser($this->owner)['events'])->toBeEmpty();
});

it('keeps cancelled recurring occurrences out after window reconciliation', function () {
    $event = $this->sync->store($this->calendar, ($this->remote)(['recurringEventId' => 'series']));
    $this->calendar->update(['sync_token' => 'old']);
    $this->travel(1)->minutes();
    Http::fakeSequence()->push(['items' => [], 'nextSyncToken' => 'new'])->push(['items' => []]);
    $this->sync->sync($this->owner->id, $this->calendar->id);
    expect($event->fresh()->removed_at)->not->toBeNull();
});

it('computes free time only inside the owner chosen 8 to 5 window and reconciles observations', function () {
    $this->sync->store($this->calendar, ($this->remote)(['start' => ['dateTime' => '2026-10-10T07:00:00-05:00'], 'end' => ['dateTime' => '2026-10-10T09:00:00-05:00']]));
    $this->sync->store($this->calendar, ($this->remote)(['id' => 'evening', 'start' => ['dateTime' => '2026-10-10T18:00:00-05:00'], 'end' => ['dateTime' => '2026-10-10T20:00:00-05:00']]));
    expect(app(CalendarBriefing::class)->tomorrow($this->owner))->toMatchArray(['minutes' => 60, 'focus_minutes' => 480]);
    app(BriefingObservations::class)->refresh($this->owner, true);
    $observation = Observation::where('rule_type', 'tomorrow_load')->firstOrFail();
    expect($observation->score)->toBe(20)->and($observation->body)->toContain('8 hours unbooked');
    app(BriefingObservations::class)->refresh($this->owner, true);
    expect(Observation::where('rule_type', 'tomorrow_load')->count())->toBe(1);
    $this->calendar->update(['error' => 'Sync unavailable']);
    app(BriefingObservations::class)->refresh($this->owner);
    expect($observation->fresh()->resolved_at)->not->toBeNull();
});

it('retains all-day dates across DST without including the exclusive end day', function () {
    $this->sync->store($this->calendar, ['id' => 'dst', 'etag' => '"day"', 'summary' => 'Day', 'start' => ['date' => '2026-11-01'], 'end' => ['date' => '2026-11-02']]);
    $start = CarbonImmutable::parse('2026-11-01', 'America/Chicago');
    expect(app(CalendarBriefing::class)->range($this->owner, $start, $start->addDay())->count())->toBe(1);
    expect(app(CalendarBriefing::class)->range($this->owner, $start->addDay(), $start->addDays(2))->count())->toBe(0);
});

it('keeps the mutation attempt durable when a worker is interrupted during a remote write', function () {
    $mutation = $this->writing->save($this->owner, ($this->data)());
    Http::fake(function ($request) {
        if ($request->method() === 'POST') {
            throw new RuntimeException('Interrupted worker');
        }

        return Http::response([], 404);
    });
    expect(fn () => $this->writing->apply($this->owner->id, $mutation->id))->toThrow(RuntimeException::class);
    expect($mutation->fresh()->attempts)->toBe(1)->and($mutation->fresh()->status)->toBe('pending');
    expect(fn () => $this->writing->discard($this->owner, $mutation->id))->toThrow(ValidationException::class);
});

it('expires OAuth attempts and rejects callbacks from another owner session', function () {
    $state = 'state';
    foreach ([['user_id' => $this->owner->id, 'expires_at' => now()->subSecond()->timestamp], ['user_id' => 999999, 'expires_at' => now()->addMinute()->timestamp]] as $pending) {
        $this->withSession(['calendar_oauth' => $pending + ['state' => hash('sha256', $state)]])->get(route('calendar.callback', ['state' => $state, 'code' => 'code']))->assertForbidden();
    }
    Http::assertNothingSent();
});

it('preserves refresh credentials on same-account reconnect without a new refresh token', function () {
    $response = $this->post(route('calendar.connect'));
    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'new', 'scope' => implode(' ', GoogleCalendarClient::SCOPES)]), 'openidconnect.googleapis.com/*' => Http::response(['sub' => $this->connection->google_subject, 'email' => $this->connection->email, 'email_verified' => true]), 'www.googleapis.com/*' => Http::response(['items' => [['id' => $this->calendar->google_id, 'summary' => 'Personal', 'accessRole' => 'owner']]])]);
    $this->get(route('calendar.callback', ['state' => $query['state'], 'code' => 'code']))->assertSessionHasNoErrors();
    expect($this->connection->fresh()->refresh_token)->toBe('test-refresh-token')->and($this->calendar->fresh()->mode)->toBe('two_way');
});

it('rejects switching Google accounts and leaves the current connection intact', function () {
    $response = $this->post(route('calendar.connect'));
    parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'new', 'refresh_token' => 'other', 'scope' => implode(' ', GoogleCalendarClient::SCOPES)]), 'openidconnect.googleapis.com/*' => Http::response(['sub' => 'different-sub', 'email' => 'other@example.test', 'email_verified' => true])]);
    $this->get(route('calendar.callback', ['state' => $query['state'], 'code' => 'code']))->assertSessionHasErrors('calendar');
    expect($this->connection->fresh()->refresh_token)->toBe('test-refresh-token');
});

it('rejects stale selections and prevents escalating a reader calendar to two-way', function () {
    $this->calendar->update(['access_role' => 'reader', 'revision' => 3]);
    $this->put(route('calendar.selection', $this->calendar), ['mode' => 'two_way', 'revision' => 0])->assertSessionHasErrors('calendar');
    $this->put(route('calendar.selection', $this->calendar), ['mode' => 'two_way', 'revision' => 3])->assertSessionHasErrors('mode');
    Http::assertNothingSent();
});
