<?php

use App\Models\ActionLog;
use App\Models\Capture;
use App\Models\CaptureItem;
use App\Models\FeedNotification;
use App\Models\Task;
use App\Models\User;
use App\Services\CaptureActions;
use App\Services\CaptureParser;
use App\Services\CaptureProcessor;
use App\Services\CaptureService;
use App\Services\WorkSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()]);
    config(['chart.owner_id' => $this->owner->id, 'chart.capture.enabled' => true, 'chart.capture.key' => 'test-only-key', 'chart.capture.connection' => 'database', 'inertia.ssr.enabled' => false]);
    app(WorkSetup::class)->initialize($this->owner);
    $this->actingAs($this->owner);
    Bus::fake();
    Http::preventStrayRequests();
    $this->capture = fn (string $text = 'Call the plumber') => app(CaptureService::class)->receive($this->owner, [
        'text' => $text, 'request_key' => Str::uuid()->toString(), 'captured_at' => now()->toIso8601String(),
    ]);
    $this->action = fn (array $overrides = []) => $overrides + [
        'type' => 'create_task', 'title' => 'Call the plumber', 'excerpt' => 'Call the plumber', 'confidence' => .95,
    ];
    $this->process = function (Capture $capture, array $actions) {
        $this->mock(CaptureParser::class)->shouldReceive('parse')->once()->andReturn(['actions' => $actions]);
        app(CaptureProcessor::class)->process($capture->id, $capture->user_id);
    };
});

it('creates a separate notification for every filing and does not duplicate retries', function () {
    $capture = ($this->capture)('Call the plumber. An idea for a garden. Someday build a shed.');
    ($this->process)($capture, [
        ($this->action)(['excerpt' => 'Call the plumber.', 'confidence' => .7]),
        ['type' => 'capture_idea', 'body' => 'An idea for a garden.', 'excerpt' => 'An idea for a garden.', 'confidence' => .95],
        ['type' => 'create_project', 'title' => 'Build a shed', 'excerpt' => 'Someday build a shed.', 'confidence' => .95, 'lifecycle' => 'someday'],
    ]);
    expect(FeedNotification::count())->toBe(3)
        ->and(FeedNotification::orderBy('id')->pluck('title')->all())->toBe(['Task added · check this', 'Idea saved', 'Someday project added']);
    foreach ($capture->items as $item) {
        app(CaptureActions::class)->execute($item);
    }
    app(CaptureProcessor::class)->process($capture->id, $capture->user_id);
    expect(FeedNotification::count())->toBe(3)->and(ActionLog::count())->toBe(3);
    $this->get('/notifications')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Work/Notifications')->where('unreadNotifications', 3)->has('notifications.data', 3)
        ->where('notifications.data.0.title', 'Someday project added')
        ->where('notifications.data.0.undo_url', route('capture-items.undo', $capture->items->last()->id))
        ->missing('notifications.data.0.undo_payload')->missing('notifications.data.0.action_log'));
    Http::assertNothingSent();
});

it('shares the existing safe undo between Intake and the feed', function () {
    $capture = ($this->capture)();
    ($this->process)($capture, [($this->action)()]);
    $item = $capture->items()->firstOrFail();
    $this->from('/notifications')->post(route('capture-items.undo', $item->id))->assertRedirect('/notifications')->assertSessionHasNoErrors();
    expect(Task::count())->toBe(0)->and(Capture::find($capture->id)->raw_text)->toBe('Call the plumber')
        ->and(FeedNotification::first()->undone_at)->not->toBeNull()->and(FeedNotification::first()->status)->toBe('read');
    $this->post(route('capture-items.undo', $item->id))->assertSessionHasNoErrors();
    expect(FeedNotification::count())->toBe(1);
    $this->get('/notifications')->assertInertia(fn (Assert $page) => $page->where('notifications.data.0.undo_url', null)->where('unreadNotifications', 0));
});

it('preserves dismissal when a filing is undone from Intake', function () {
    $capture = ($this->capture)();
    ($this->process)($capture, [($this->action)()]);
    FeedNotification::first()->update(['status' => 'dismissed']);
    $this->post(route('capture-items.undo', $capture->items()->first()->id))->assertSessionHasNoErrors();
    expect(FeedNotification::first()->status)->toBe('dismissed')->and(FeedNotification::first()->undone_at)->not->toBeNull();
});

it('rejects undo of edited records without losing the notification or the task', function () {
    $capture = ($this->capture)();
    ($this->process)($capture, [($this->action)()]);
    Task::first()->update(['title' => 'Call the plumber tomorrow instead']);
    $this->from('/notifications')->post(route('capture-items.undo', $capture->items()->first()->id))->assertSessionHasErrors('undo');
    expect(Task::count())->toBe(1)->and(FeedNotification::first()->undone_at)->toBeNull();
});

it('hides expired undo and enforces the same seven day limit on the server', function () {
    $capture = ($this->capture)();
    ($this->process)($capture, [($this->action)()]);
    $this->travel(8)->days();
    $this->get('/notifications')->assertInertia(fn (Assert $page) => $page->where('notifications.data.0.undo_url', null));
    $this->post(route('capture-items.undo', $capture->items()->first()->id))->assertSessionHasErrors('undo');
    expect(Task::count())->toBe(1);
});

it('deduplicates review notices and resolves them after the final item is filed', function () {
    $capture = ($this->capture)('Call the plumber. Meet someone.');
    ($this->process)($capture, [($this->action)(['excerpt' => 'Call the plumber.']), [
        'type' => 'needs_triage', 'excerpt' => 'Meet someone.', 'reason' => 'Choose who to meet.', 'confidence' => .4,
    ]]);
    app(CaptureService::class)->summarize($capture);
    expect(FeedNotification::count())->toBe(2)->and(FeedNotification::where('type', 'capture_review')->count())->toBe(1);
    $item = $capture->items()->where('status', 'needs_triage')->firstOrFail();
    app(CaptureActions::class)->execute($item, ($this->action)(['title' => 'Arrange lunch with Dan']));
    app(CaptureService::class)->summarize($capture);
    expect(FeedNotification::count())->toBe(3)->and(FeedNotification::where('type', 'capture_review')->count())->toBe(0)
        ->and(FeedNotification::where('type', 'capture_resolved')->first()->status)->toBe('read');
});

it('updates failure notices during retries without leaving a stale error after success', function () {
    $capture = ($this->capture)();
    app(CaptureService::class)->fallback($capture, 'Sorting failed. Your words are saved.');
    app(CaptureService::class)->fallback($capture, 'Sorting failed again. Your words are saved.');
    expect(FeedNotification::count())->toBe(1)->and(FeedNotification::first()->body)->toBe('Sorting failed again. Your words are saved.');
    $this->post(route('captures.retry', $capture->id))->assertSessionHasNoErrors();
    expect(FeedNotification::first()->type)->toBe('capture_sorting')->and(FeedNotification::first()->status)->toBe('read');
    ($this->process)($capture->fresh(), [($this->action)()]);
    expect(FeedNotification::where('type', 'capture_resolved')->count())->toBe(1)
        ->and(FeedNotification::where('type', 'capture_filed')->count())->toBe(1)
        ->and(Task::count())->toBe(1);
});

it('keeps attention visible if the fallback copy was edited before retry', function () {
    $capture = ($this->capture)();
    app(CaptureService::class)->fallback($capture, 'Sorting failed.');
    Task::first()->update(['title' => 'An edited fallback']);
    $this->post(route('captures.retry', $capture->id))->assertSessionHasNoErrors();
    ($this->process)($capture->fresh(), [($this->action)()]);
    expect(FeedNotification::count())->toBe(1)->and(FeedNotification::first()->type)->toBe('capture_review')
        ->and(FeedNotification::first()->status)->toBe('unread')
        ->and(FeedNotification::first()->body)->toContain('Inbox copy has been changed');
});

it('does not revive a dismissed error on each automatic retry', function () {
    $capture = ($this->capture)();
    app(CaptureService::class)->fallback($capture, 'Saved to Inbox. Another attempt is scheduled.');
    FeedNotification::first()->update(['status' => 'dismissed']);
    $this->post(route('captures.retry', $capture->id))->assertSessionHasNoErrors();
    app(CaptureService::class)->fallback($capture->fresh(), 'Saved to Inbox. Another attempt failed.');
    expect(FeedNotification::count())->toBe(1)->and(FeedNotification::first()->status)->toBe('dismissed');
});

it('supports read dismiss restore and mark all without changing captured records or other owners', function () {
    $notice = FeedNotification::factory()->create(['user_id' => $this->owner->id]);
    $other = FeedNotification::factory()->create();
    $this->patch(route('notifications.update', $notice->id), ['status' => 'read'])->assertSessionHasNoErrors();
    expect($notice->fresh()->status)->toBe('read');
    $this->patch(route('notifications.update', $notice->id), ['status' => 'dismissed'])->assertSessionHasNoErrors();
    $this->get('/notifications?status=dismissed')->assertInertia(fn (Assert $page) => $page->has('notifications.data', 1)->where('unreadNotifications', 0));
    $this->post(route('notifications.read-all'))->assertSessionHasNoErrors();
    expect($notice->fresh()->status)->toBe('dismissed')->and($other->fresh()->status)->toBe('unread');
    $this->patch(route('notifications.update', $notice->id), ['status' => 'unread'])->assertSessionHasNoErrors();
    $this->post(route('notifications.read-all'))->assertSessionHasNoErrors();
    expect($notice->fresh()->status)->toBe('read');
    $this->patch(route('notifications.update', $other->id), ['status' => 'read'])->assertNotFound();
    $this->patch(route('notifications.update', $notice->id), ['status' => 'invalid'])->assertSessionHasErrors('status');
});

it('paginates owned notifications and filters unread without exposing private undo snapshots', function () {
    FeedNotification::factory()->count(23)->create(['user_id' => $this->owner->id]);
    FeedNotification::factory()->create(['user_id' => $this->owner->id, 'status' => 'read']);
    FeedNotification::factory()->create(['user_id' => $this->owner->id, 'status' => 'dismissed']);
    FeedNotification::factory()->create(['body' => 'Another owner private text']);
    $this->get('/notifications')->assertInertia(fn (Assert $page) => $page->has('notifications.data', 20)->where('notifications.total', 24)->where('unreadNotifications', 23));
    $this->get('/notifications?status=unread&page=2')->assertInertia(fn (Assert $page) => $page->has('notifications.data', 3)->where('notifications.total', 23)->where('filter', 'unread'));
    $this->get('/notifications?status=invalid')->assertSessionHasErrors('status');
    expect($this->get('/notifications')->headers->get('Cache-Control'))->toContain('no-store');
    Http::assertNothingSent();
});

it('requires owner authentication and confirmed two factor for every notification endpoint', function () {
    $notice = FeedNotification::factory()->create(['user_id' => $this->owner->id]);
    $this->owner->update(['two_factor_confirmed_at' => null]);
    $this->get('/notifications')->assertRedirect('/user/profile');
    $this->patchJson(route('notifications.update', $notice->id), ['status' => 'read'])->assertForbidden();
    $this->postJson(route('notifications.read-all'))->assertForbidden();
    $this->actingAs(User::factory()->create(['two_factor_confirmed_at' => now()]));
    $this->get('/notifications')->assertForbidden();
    $this->patchJson(route('notifications.update', $notice->id), ['status' => 'read'])->assertForbidden();
    $this->postJson(route('notifications.read-all'))->assertForbidden();
    auth()->forgetGuards();
    $this->get('/notifications')->assertRedirect('/login');
    expect($notice->fresh()->status)->toBe('unread');
});

it('rolls back the filing if its notification cannot be persisted and safely retries', function () {
    $capture = ($this->capture)();
    FeedNotification::creating(function () {
        throw new RuntimeException('Simulated notification storage failure');
    });
    try {
        ($this->process)($capture, [($this->action)()]);
    } catch (RuntimeException) {
    } finally {
        FeedNotification::flushEventListeners();
    }
    expect(Task::count())->toBe(0)->and(ActionLog::count())->toBe(0)->and(FeedNotification::count())->toBe(0);
    $item = CaptureItem::firstOrFail();
    app(CaptureActions::class)->execute($item);
    app(CaptureService::class)->summarize($capture);
    expect(Task::count())->toBe(1)->and(ActionLog::count())->toBe(1)->and(FeedNotification::count())->toBe(1);
});
