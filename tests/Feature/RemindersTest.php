<?php

use App\Jobs\SendBrowserPush;
use App\Models\AppSetting;
use App\Models\CalendarConnection;
use App\Models\ConnectedCalendar;
use App\Models\FeedNotification;
use App\Models\PushDelivery;
use App\Models\PushSubscription;
use App\Models\Reminder;
use App\Models\Task;
use App\Models\User;
use App\Services\BrowserPush;
use App\Services\CalendarSync;
use App\Services\ReminderDelivery;
use App\Services\TaskCompletion;
use App\Services\WorkSetup;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09T12:00:00Z'));
    $this->owner = User::factory()->create(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()]);
    config(['chart.owner_id' => $this->owner->id, 'inertia.ssr.enabled' => false, 'chart.push.public_key' => 'test-public', 'chart.push.private_key' => 'test-private', 'chart.push.subject' => 'https://chart.test']);
    $this->actingAs($this->owner);
    app(WorkSetup::class)->initialize($this->owner);
    AppSetting::forUser($this->owner)->updateOrCreate(['key' => 'timezone'], ['user_id' => $this->owner->id, 'value' => 'America/Chicago']);
    $this->domain = app(WorkSetup::class)->inbox($this->owner);
    $this->task = Task::create(['user_id' => $this->owner->id, 'domain_id' => $this->domain->id, 'title' => 'Send invoice', 'due_date' => '2026-10-09', 'due_time' => '08:00', 'reminder_offsets' => [15, 0]]);
    Bus::fake();
});

it('plans future reminders and delivers each offset once in the owner timezone', function () {
    $subscription = PushSubscription::factory()->create(['user_id' => $this->owner->id]);
    $service = app(ReminderDelivery::class);
    $service->run($this->owner);
    expect(Reminder::count())->toBe(2)->and(FeedNotification::count())->toBe(0);
    $this->travelTo(CarbonImmutable::parse('2026-10-09T12:45:00Z'));
    $service->run($this->owner);
    $service->run($this->owner);
    expect(FeedNotification::count())->toBe(1)->and(PushDelivery::count())->toBe(1);
    expect(FeedNotification::sole()->body)->toBe('Send invoice');
    $this->travel(15)->minutes();
    $service->run($this->owner);
    expect(FeedNotification::count())->toBe(2)->and(PushDelivery::count())->toBe(2);
    Bus::assertDispatched(SendBrowserPush::class);
});

it('cancels changed and completed work and never floods historical deadlines', function () {
    $service = app(ReminderDelivery::class);
    $service->run($this->owner);
    $this->task->update(['due_time' => '09:00']);
    $service->run($this->owner);
    expect(Reminder::where('status', 'cancelled')->count())->toBe(2);
    $this->task->update(['completed_at' => now()]);
    $service->run($this->owner);
    expect(Reminder::where('status', 'pending')->count())->toBe(0);
    $this->task->update(['completed_at' => null, 'due_date' => '2026-09-01']);
    $service->run($this->owner);
    expect(FeedNotification::count())->toBe(0);
});

it('supports default reminders and explicit none and skips date-only tasks', function () {
    $this->task->update(['due_time' => '07:00', 'reminder_offsets' => []]);
    app(ReminderDelivery::class)->run($this->owner);
    expect(FeedNotification::count())->toBe(0);
    $this->task->update(['reminder_offsets' => null]);
    app(ReminderDelivery::class)->run($this->owner);
    expect(FeedNotification::count())->toBe(1);
    $this->task->update(['due_time' => null]);
    expect(app(ReminderDelivery::class)->candidates($this->owner))->toBe([]);
});

it('delivers planned reminders after a short outage but expires ones over a day late', function () {
    app(ReminderDelivery::class)->run($this->owner);
    $this->travel(2)->hours();
    app(ReminderDelivery::class)->run($this->owner);
    expect(FeedNotification::count())->toBe(2);
    $this->task->update(['due_date' => '2026-10-10']);
    app(ReminderDelivery::class)->run($this->owner);
    $this->travel(3)->days();
    app(ReminderDelivery::class)->run($this->owner);
    expect(Reminder::where('status', 'expired')->count())->toBe(2)->and(FeedNotification::count())->toBe(2);
});

it('requires calendar reminder opt-in and skips cancelled or stale events', function () {
    $this->task->delete();
    $connection = CalendarConnection::factory()->create(['user_id' => $this->owner->id]);
    $calendar = ConnectedCalendar::factory()->create(['user_id' => $this->owner->id, 'calendar_connection_id' => $connection->id, 'mode' => 'read_only', 'last_synced_at' => now()]);
    app(CalendarSync::class)->store($calendar, ['id' => 'event', 'etag' => 'v1', 'summary' => 'Planning', 'start' => ['dateTime' => '2026-10-09T07:15:00-05:00'], 'end' => ['dateTime' => '2026-10-09T08:00:00-05:00']]);
    app(ReminderDelivery::class)->run($this->owner);
    expect(FeedNotification::count())->toBe(0);
    $calendar->update(['reminder_minutes' => 15]);
    app(ReminderDelivery::class)->run($this->owner);
    expect(FeedNotification::sole()->title)->toBe('Calendar reminder');
    $calendar->update(['last_synced_at' => now()->subHour()]);
    expect(app(ReminderDelivery::class)->candidates($this->owner))->toBe([]);
});

it('encrypts subscriptions, scopes settings and rejects arbitrary endpoints', function () {
    $subscription = PushSubscription::factory()->make(['user_id' => $this->owner->id]);
    $data = $subscription->subscription + ['label' => 'Phone'];
    $this->postJson(route('push.store'), $data)->assertOk();
    $this->postJson(route('push.store'), $data)->assertOk();
    expect(PushSubscription::count())->toBe(1)->and(DB::table('push_subscriptions')->value('subscription'))->not->toContain('fcm.googleapis.com');
    $this->get(route('push.settings'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('subscriptions', 1)->missing('subscriptions.0.subscription')->missing('privateKey'));
    $this->postJson(route('push.store'), array_replace($data, ['endpoint' => 'https://127.0.0.1/push']))->assertUnprocessable();
    $other = PushSubscription::factory()->create();
    $this->delete(route('push.destroy', $other->id))->assertNotFound();
    $this->post(route('push.test', $other->id))->assertNotFound();
    $this->post(route('logout'))->assertRedirect();
    expect(PushSubscription::forUser($this->owner)->count())->toBe(0);
});

it('deduplicates completed push jobs and retries transient errors', function () {
    $delivery = PushDelivery::factory()->create(['user_id' => $this->owner->id, 'push_subscription_id' => PushSubscription::factory()->create(['user_id' => $this->owner->id])->id]);
    $push = Mockery::mock(BrowserPush::class);
    $push->shouldReceive('configured')->andReturn(true);
    $push->shouldReceive('send')->once()->andReturn('retry');
    $job = new SendBrowserPush($this->owner->id, $delivery->id);
    $job->handle($push);
    $job->handle($push);
    expect($delivery->fresh()->attempts)->toBe(1)->and($delivery->fresh()->status)->toBe('pending');
    $this->travel(3)->minutes();
    $success = Mockery::mock(BrowserPush::class);
    $success->shouldReceive('configured')->andReturn(true);
    $success->shouldReceive('send')->once()->andReturn('sent');
    $job->handle($success);
    $job->handle($success);
    expect($delivery->fresh()->status)->toBe('sent')->and($delivery->fresh()->attempts)->toBe(2);
});

it('does not send an obsolete reminder after task completion', function () {
    PushSubscription::factory()->create(['user_id' => $this->owner->id]);
    $this->task->update(['due_time' => '07:00', 'reminder_offsets' => [0]]);
    app(ReminderDelivery::class)->run($this->owner);
    $delivery = PushDelivery::sole();
    $this->task->update(['completed_at' => now()]);
    $push = Mockery::mock(BrowserPush::class);
    $push->shouldReceive('configured')->andReturn(true);
    $push->shouldNotReceive('send');
    (new SendBrowserPush($this->owner->id, $delivery->id))->handle($push);
    expect($delivery->fresh()->status)->toBe('cancelled');
});

it('removes expired subscriptions', function () {
    $subscription = PushSubscription::factory()->create(['user_id' => $this->owner->id]);
    $delivery = PushDelivery::factory()->create(['user_id' => $this->owner->id, 'push_subscription_id' => $subscription->id]);
    $push = Mockery::mock(BrowserPush::class);
    $push->shouldReceive('configured')->andReturn(true);
    $push->shouldReceive('send')->once()->andReturn('expired');
    (new SendBrowserPush($this->owner->id, $delivery->id))->handle($push);
    expect(PushSubscription::find($subscription->id))->toBeNull()->and(PushDelivery::find($delivery->id))->toBeNull();
});

it('preserves reminder offsets on recurring successors and validates clock changes', function () {
    $this->post(route('tasks.store'), ['title' => 'Ambiguous clock', 'due_date' => '2026-11-01', 'due_time' => '01:30'])->assertSessionHasErrors('due_time');
    $this->task->update(['recurrence_rule' => 'FREQ=DAILY;INTERVAL=1', 'recurrence_anchor' => '2026-10-09', 'recurrence_index' => 0, 'recurrence_timezone' => 'America/Chicago']);
    app(TaskCompletion::class)->complete($this->owner, $this->task, CarbonImmutable::now());
    expect(Task::where('recurrence_parent_id', $this->task->id)->sole()->reminder_offsets)->toBe([15, 0]);
});

it('validates malformed subscriptions without leaking errors or writing data', function () {
    $this->postJson(route('push.store'), ['label' => 'Bad', 'endpoint' => ['invalid'], 'keys' => ['p256dh' => [], 'auth' => []]])->assertUnprocessable();
    expect(PushSubscription::count())->toBe(0);
});
