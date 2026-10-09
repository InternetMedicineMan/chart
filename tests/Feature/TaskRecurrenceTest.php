<?php

use App\Models\ActionLog;
use App\Models\Capture;
use App\Models\CaptureItem;
use App\Models\DailyPlan;
use App\Models\Domain;
use App\Models\FeedNotification;
use App\Models\Person;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\CaptureActions;
use App\Services\TaskRecurrence;
use App\Services\WorkSetup;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09T18:00:00Z'));
    $this->owner = User::factory()->create(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()]);
    config(['chart.owner_id' => $this->owner->id, 'inertia.ssr.enabled' => false]);
    $this->actingAs($this->owner);
    Http::preventStrayRequests();
    app(WorkSetup::class)->initialize($this->owner);
    $this->domain = Domain::forUser($this->owner)->where('name', 'Writing')->firstOrFail();
    $this->project = Project::create(['user_id' => $this->owner->id, 'domain_id' => $this->domain->id, 'name' => 'Book', 'slug' => 'book', 'type' => 'ongoing', 'lifecycle' => 'active']);
    $this->data = fn (array $extra = []) => $extra + ['title' => 'Write article', 'domain_id' => $this->domain->id, 'project_id' => $this->project->id, 'due_date' => '2026-10-02', 'due_time' => '09:00', 'recurrence_rule' => 'FREQ=WEEKLY;INTERVAL=1'];
    $this->make = function (array $extra = []) {
        $this->post(route('tasks.store'), ($this->data)($extra))->assertSessionHasNoErrors();

        return Task::latest('id')->firstOrFail();
    };
    $this->complete = fn (Task $task) => $this->patch(route('tasks.completion', $task), ['completed' => true, 'revision' => $task->fresh()->revision]);
    $this->capture = function (Task $task, array $payload = []) {
        $capture = Capture::create(['user_id' => $this->owner->id, 'request_key' => Str::uuid()->toString(), 'raw_text' => 'Mark Write article done', 'source' => 'in_app', 'mode' => 'single', 'timezone' => 'America/Chicago', 'client_captured_at' => now()->subHour(), 'status' => 'parsed']);

        return CaptureItem::create(['user_id' => $this->owner->id, 'capture_id' => $capture->id, 'sequence' => 0, 'action_type' => 'complete_task', 'excerpt' => $capture->raw_text, 'status' => 'pending', 'payload' => $payload + ['type' => 'complete_task', 'task_ref' => $task->title, 'confidence' => .95, 'excerpt' => $capture->raw_text]]);
    };
});

it('creates one future occurrence on the original schedule and keeps retries idempotent', function () {
    $task = ($this->make)();
    ($this->complete)($task)->assertSessionHasNoErrors();
    ($this->complete)($task)->assertSessionHasNoErrors();
    $next = Task::where('recurrence_parent_id', $task->id)->firstOrFail();
    expect(Task::count())->toBe(2)->and($next->due_date->toDateString())->toBe('2026-10-16')->and($next->recurrence_anchor->toDateString())->toBe('2026-10-02')
        ->and($next->due_time)->toStartWith('09:00')->and($next->completed_at)->toBeNull()->and($next->waiting_on_person_id)->toBeNull()
        ->and($next->recurrence_timezone)->toBe('America/Chicago')->and(DB::table('task_completions')->count())->toBe(1);
    $this->patch(route('tasks.completion', $task), ['completed' => false, 'revision' => $task->fresh()->revision])->assertSessionHasNoErrors();
    expect(Task::withTrashed()->count())->toBe(1)->and($task->fresh()->completed_at)->toBeNull()->and($this->project->fresh()->last_touched_at)->not->toBeNull();
    ($this->complete)($task)->assertSessionHasNoErrors();
    expect(Task::count())->toBe(2);
});

it('computes calendar anchored dates including intervals month ends leap days and DST', function (string $anchor, string $rule, string $when, ?string $time, ?string $expected) {
    $task = ($this->make)(['due_date' => $anchor, 'recurrence_rule' => $rule, 'due_time' => $time]);
    $next = app(TaskRecurrence::class)->next($task, CarbonImmutable::parse($when));
    expect($next['due_date'] ?? null)->toBe($expected);
})->with([
    ['2026-01-31', 'FREQ=MONTHLY', '2026-02-02T18:00:00Z', null, '2026-03-31'],
    ['2024-02-29', 'FREQ=YEARLY', '2025-03-01T18:00:00Z', null, '2028-02-29'],
    ['2026-10-02', 'FREQ=WEEKLY;INTERVAL=2', '2026-10-20T18:00:00Z', null, '2026-10-30'],
    ['2026-03-07', 'FREQ=DAILY', '2026-03-07T18:00:00Z', '02:30', '2026-03-09'],
    ['2026-10-31', 'FREQ=DAILY', '2026-11-01T02:00:00Z', '09:00', '2026-11-01'],
    ['2026-10-02', 'FREQ=WEEKLY;UNTIL=20261009', '2026-10-02T18:00:00Z', null, '2026-10-09'],
    ['2026-10-02', 'FREQ=WEEKLY;UNTIL=20261009', '2026-10-09T18:00:00Z', null, null],
]);

it('rejects unsupported malformed and dateless repeats instead of discarding their meaning', function (array $extra, string $field) {
    $this->post(route('tasks.store'), ($this->data)($extra))->assertSessionHasErrors($field);
    expect(Task::count())->toBe(0);
})->with([
    [['recurrence_rule' => 'FREQ=WEEKLY;BYDAY=MO,WE'], 'recurrence_rule'],
    [['recurrence_rule' => 'FREQ=DAILY;COUNT=5'], 'recurrence_rule'],
    [['recurrence_rule' => 'FREQ=MONTHLY;INTERVAL=0'], 'recurrence_rule'],
    [['recurrence_rule' => 'FREQ=MONTHLY;INTERVAL=366'], 'recurrence_rule'],
    [['recurrence_rule' => 'FREQ=DAILY;FREQ=WEEKLY'], 'recurrence_rule'],
    [['recurrence_rule' => 'FREQ=WEEKLY;UNTIL=20260230'], 'recurrence_rule'],
    [['recurrence_rule' => 'FREQ=WEEKLY;UNTIL=20260901'], 'recurrence_rule'],
    [['due_date' => null, 'due_time' => null], 'due_date'],
]);

it('allows changing or stopping the open repeat and protects stale or historical forms', function () {
    $task = ($this->make)();
    $this->put(route('tasks.update', $task), ($this->data)(['revision' => 0, 'due_date' => '2026-10-12', 'recurrence_rule' => 'FREQ=MONTHLY']))->assertSessionHasNoErrors();
    expect($task->fresh()->recurrence_anchor->toDateString())->toBe('2026-10-12');
    $this->put(route('tasks.update', $task), ($this->data)(['revision' => 0]))->assertSessionHasErrors('revision');
    $this->put(route('tasks.update', $task), ($this->data)(['revision' => 1, 'due_date' => null, 'due_time' => null]))->assertSessionHasErrors('due_date');
    $this->patch(route('tasks.completion', $task), ['completed' => true, 'revision' => 0])->assertSessionHasErrors('revision');
    ($this->complete)($task)->assertSessionHasNoErrors();
    $this->put(route('tasks.update', $task), ($this->data)(['revision' => $task->fresh()->revision, 'recurrence_rule' => null]))->assertSessionHasErrors('recurrence_rule');
    $next = Task::where('recurrence_parent_id', $task->id)->firstOrFail();
    $this->put(route('tasks.update', $next), ($this->data)(['revision' => $next->revision, 'recurrence_rule' => null]))->assertSessionHasNoErrors();
    ($this->complete)($next)->assertSessionHasNoErrors();
    expect(Task::count())->toBe(2);
});

it('protects the generated occurrence if edited completed deleted or planned', function (string $change) {
    $task = ($this->make)();
    ($this->complete)($task)->assertSessionHasNoErrors();
    $next = Task::where('recurrence_parent_id', $task->id)->firstOrFail();
    match ($change) {
        'edit' => $next->update(['notes' => 'Keep my newer work']),
        'complete' => ($this->complete)($next)->assertSessionHasNoErrors(),
        'delete' => $next->delete(),
        'plan' => DailyPlan::create(['user_id' => $this->owner->id, 'plan_date' => '2026-10-09', 'top_task_ids' => [$next->id]]),
    };
    $this->patch(route('tasks.completion', $task), ['completed' => false, 'revision' => $task->fresh()->revision])->assertSessionHasErrors('completion');
    expect($task->fresh()->completed_at)->not->toBeNull()->and(Task::withTrashed()->find($next->id))->not->toBeNull();
})->with(['edit', 'complete', 'delete', 'plan']);

it('completes from capture once and undoes the occurrence wait and only its own touch', function () {
    $task = ($this->make)();
    $person = Person::create(['user_id' => $this->owner->id, 'name' => 'Alex', 'relationship' => 'other']);
    $task->update(['waiting_on_person_id' => $person->id, 'waiting_since' => now()->subDay(), 'wait_expected_by' => '2026-10-12']);
    $this->travel(1)->days();
    $item = ($this->capture)($task);
    app(CaptureActions::class)->execute($item);
    app(CaptureActions::class)->execute($item);
    expect($item->fresh()->status)->toBe('executed')->and($task->fresh()->completed_at->equalTo(now()->subHour()))->toBeTrue()
        ->and($task->fresh()->waiting_on_person_id)->toBeNull()->and(Task::count())->toBe(2)->and(FeedNotification::where('title', 'Task completed')->count())->toBe(1);
    $this->post(route('activity.store'), ['subject_type' => 'project', 'subject_id' => $this->project->id, 'entry' => 'Later work', 'minutes' => 10, 'occurred_local' => now()->setTimezone('America/Chicago')->format('Y-m-d\TH:i'), 'timezone' => 'America/Chicago', 'request_key' => Str::uuid()->toString(), 'revision' => 0])->assertSessionHasNoErrors();
    $this->post(route('capture-items.undo', $item))->assertSessionHasNoErrors();
    expect($task->fresh()->completed_at)->toBeNull()->and($task->fresh()->waiting_on_person_id)->toBe($person->id)->and($task->fresh()->wait_expected_by->toDateString())->toBe('2026-10-12')
        ->and($item->fresh()->status)->toBe('undone')->and(Task::withTrashed()->count())->toBe(1)->and($this->project->fresh()->last_touched_at->equalTo(now()))->toBeTrue();
    app(CaptureActions::class)->execute($item);
    expect(Task::count())->toBe(1)->and(Capture::count())->toBe(1);
});

it('refuses stale ambiguous future-repeat and low-confidence automatic completions', function (string $case) {
    $task = ($this->make)();
    $this->travel(1)->days();
    $payload = [];
    match ($case) {
        'stale' => $task->update(['notes' => 'Changed since recording']),
        'ambiguous' => ($this->make)(),
        'future' => $task->update(['due_date' => now()->addWeek()->toDateString(), 'updated_at' => now()->subDay()]),
        'low' => $payload = ['confidence' => .79],
        'fuzzy' => $payload = ['task_ref' => 'article'],
    };
    $item = ($this->capture)($task, $payload);
    app(CaptureActions::class)->execute($item);
    expect($item->fresh()->status)->toBe('needs_triage')->and($task->fresh()->completed_at)->toBeNull()->and(ActionLog::count())->toBe(0);
})->with(['stale', 'ambiguous', 'future', 'low', 'fuzzy']);

it('requires the reviewed task revision and protects subsequent edits on undo', function () {
    $task = ($this->make)();
    $item = ($this->capture)($task);
    $task->update(['notes' => 'New details']);
    $this->put(route('capture-items.resolve', $item), ['type' => 'complete_task', 'task_id' => $task->id, 'task_revision' => 0])->assertSessionHasNoErrors();
    expect($item->fresh()->status)->toBe('needs_triage');
    $this->put(route('capture-items.resolve', $item), ['type' => 'complete_task', 'task_id' => $task->id, 'task_revision' => $task->fresh()->revision])->assertSessionHasNoErrors();
    expect($item->fresh()->status)->toBe('executed');
    $task->refresh()->update(['notes' => 'Preserve this']);
    $this->post(route('capture-items.undo', $item))->assertSessionHasErrors('undo');
    expect(Task::count())->toBe(2);
});

it('does not let a replayed recording complete the newly generated occurrence', function () {
    $task = ($this->make)();
    $this->travel(1)->days();
    $item = ($this->capture)($task);
    app(CaptureActions::class)->execute($item);
    $duplicate = ($this->capture)($task);
    app(CaptureActions::class)->execute($duplicate);
    expect($duplicate->fresh()->status)->toBe('needs_triage')->and(Task::count())->toBe(2)->and(Task::whereNotNull('completed_at')->count())->toBe(1);
});

it('restores an earlier completion touch when a later captured completion is undone', function () {
    $task = ($this->make)(['recurrence_rule' => null]);
    ($this->complete)($task)->assertSessionHasNoErrors();
    $originalTouch = $this->project->fresh()->last_touched_at->toISOString();
    $this->patch(route('tasks.completion', $task), ['completed' => false, 'revision' => $task->fresh()->revision])->assertSessionHasNoErrors();
    $this->travel(2)->days();
    $item = ($this->capture)($task);
    app(CaptureActions::class)->execute($item);
    expect($this->project->fresh()->last_touched_at->toISOString())->not->toBe($originalTouch);
    $this->post(route('capture-items.undo', $item))->assertSessionHasNoErrors();
    expect($this->project->fresh()->last_touched_at->toISOString())->toBe($originalTouch)->and($task->fresh()->completed_at)->toBeNull();
});

it('keeps the repeat timezone after account settings change and advances past offline upload day', function () {
    $task = ($this->make)(['due_date' => '2026-10-08', 'recurrence_rule' => 'FREQ=DAILY']);
    $this->put(route('work.timezone'), ['timezone' => 'Asia/Tokyo'])->assertSessionHasNoErrors();
    $this->travelTo(CarbonImmutable::parse('2026-10-12T02:00:00Z'));
    $item = ($this->capture)($task);
    Capture::whereKey($item->capture_id)->update(['client_captured_at' => '2026-10-09 19:00:00']);
    app(CaptureActions::class)->execute($item);
    $next = Task::where('recurrence_parent_id', $task->id)->firstOrFail();
    expect($next->due_date->toDateString())->toBe('2026-10-12')->and($next->recurrence_timezone)->toBe('America/Chicago')
        ->and($task->fresh()->completed_at->toISOString())->toBe('2026-10-09T19:00:00.000000Z');
});

it('rolls back completion when recurrence cannot be interpreted', function () {
    $task = ($this->make)();
    $task->update(['recurrence_rule' => 'FREQ=HOURLY']);
    ($this->complete)($task)->assertSessionHasErrors('recurrence_rule');
    expect($task->fresh()->completed_at)->toBeNull()->and(Task::count())->toBe(1)->and(DB::table('work_touches')->count())->toBe(0);
});

it('previews completion without writes and honors ownership and the undo window', function () {
    $task = ($this->make)();
    $this->travel(1)->days();
    config(['chart.capture.key' => 'test-only']);
    $action = ['type' => 'complete_task', 'task_ref' => $task->title, 'confidence' => .95, 'excerpt' => 'Mark Write article done'];
    Http::fake(['*' => Http::response(['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode(['actions' => [$action]])]]]]])]);
    $this->postJson(route('parser.preview'), ['text' => $action['excerpt'], 'captured_at' => now()->subHour()->toISOString()])->assertOk()->assertJsonPath('items.0.outcome', 'would_file');
    expect($task->fresh()->completed_at)->toBeNull()->and(Task::count())->toBe(1)->and(Capture::count())->toBe(0);
    $item = ($this->capture)($task);
    $foreign = Task::create(['user_id' => User::factory()->create()->id, 'domain_id' => $this->domain->id, 'title' => 'Private task']);
    $this->put(route('capture-items.resolve', $item), ['type' => 'complete_task', 'task_id' => $foreign->id, 'task_revision' => 0])->assertSessionHasErrors('task_id');
    app(CaptureActions::class)->execute($item);
    $this->travel(8)->days();
    $this->post(route('capture-items.undo', $item))->assertSessionHasErrors('undo');
});

it('backfills old task audit and fallback snapshots so deployment preserves undo', function () {
    $task = ($this->make)(['recurrence_rule' => null]);
    $capture = Capture::create(['user_id' => $this->owner->id, 'request_key' => Str::uuid()->toString(), 'raw_text' => 'Write article', 'source' => 'in_app', 'mode' => 'single', 'timezone' => 'America/Chicago', 'client_captured_at' => now(), 'fallback_snapshot' => $task->fresh()->getRawOriginal()]);
    $item = CaptureItem::create(['user_id' => $this->owner->id, 'capture_id' => $capture->id, 'sequence' => 0, 'action_type' => 'create_task', 'excerpt' => 'Write article', 'payload' => [], 'status' => 'executed', 'target_type' => 'task', 'target_id' => $task->id]);
    $log = ActionLog::create(['user_id' => $this->owner->id, 'capture_id' => $capture->id, 'capture_item_id' => $item->id, 'action_type' => 'create_task', 'target_type' => 'task', 'target_id' => $task->id, 'payload' => [], 'before_snapshot' => $task->fresh()->getRawOriginal(), 'after_snapshot' => $task->fresh()->getRawOriginal(), 'status' => 'ok', 'executed_at' => now()]);
    $migration = require database_path('migrations/2026_10_09_163348_add_task_recurrence_and_completion_history.php');
    $migration->down();
    expect($log->fresh()->after_snapshot)->not->toHaveKey('revision');
    $migration->up();
    $snapshot = $task->fresh()->getRawOriginal();
    expect($log->fresh()->before_snapshot)->toEqual($snapshot)->and($log->fresh()->after_snapshot)->toEqual($snapshot)->and($capture->fresh()->fallback_snapshot)->toEqual($snapshot);
    $this->post(route('capture-items.undo', $item))->assertSessionHasNoErrors();
    expect($task->fresh()->trashed())->toBeTrue();
});
