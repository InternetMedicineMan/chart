<?php

use App\Models\ActionLog;
use App\Models\ActivityLog;
use App\Models\Capture;
use App\Models\CaptureItem;
use App\Models\Domain;
use App\Models\FeedNotification;
use App\Models\Person;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\CaptureActions;
use App\Services\CaptureProcessor;
use App\Services\CaptureService;
use App\Services\WorkSetup;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09T12:00:00Z'));
    $this->owner = User::factory()->create(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()]);
    config(['chart.owner_id' => $this->owner->id, 'inertia.ssr.enabled' => false, 'chart.capture.enabled' => true, 'chart.capture.key' => 'test-only-key']);
    $this->actingAs($this->owner);
    app(WorkSetup::class)->initialize($this->owner);
    Bus::fake();
    Http::preventStrayRequests();
    $this->domain = Domain::forUser($this->owner)->where('name', 'Writing')->firstOrFail();
    $this->project = Project::create(['user_id' => $this->owner->id, 'domain_id' => $this->domain->id, 'name' => 'Book', 'slug' => 'book', 'type' => 'ongoing', 'lifecycle' => 'active']);
    $this->task = Task::create(['user_id' => $this->owner->id, 'domain_id' => $this->domain->id, 'project_id' => $this->project->id, 'title' => 'Cover design', 'due_date' => '2026-10-20']);
    $this->person = Person::create(['user_id' => $this->owner->id, 'name' => 'Alex', 'relationship' => 'other']);
    $this->travel(1)->days();
    $this->receive = function (string $text = 'Log 45 minutes on Book') {
        $this->postJson('/captures', ['text' => $text, 'request_key' => Str::uuid()->toString(), 'captured_at' => now()->subHour()->toISOString(), 'owner_id' => $this->owner->id])->assertAccepted();

        return Capture::latest('id')->firstOrFail();
    };
    $this->item = function (array $data = []) {
        $capture = ($this->receive)($data['excerpt'] ?? 'Log 45 minutes on Book');
        $payload = $data + ['type' => 'log_activity', 'body' => 'Worked on the book', 'project_ref' => 'Book', 'minutes' => 45, 'confidence' => .95, 'excerpt' => $capture->raw_text];

        return CaptureItem::create(['user_id' => $this->owner->id, 'capture_id' => $capture->id, 'sequence' => 0, 'action_type' => $payload['type'], 'excerpt' => $payload['excerpt'], 'payload' => $payload, 'status' => 'pending']);
    };
    $this->wait = fn (array $data = []) => ($this->item)($data + ['type' => 'set_waiting', 'body' => null, 'minutes' => null, 'task_ref' => 'Cover design', 'person_ref' => 'Alex', 'expected_by' => '2026-10-15', 'excerpt' => 'Waiting on Alex for Cover design in Book, expect October 15']);
});

it('logs activity once at recording time and shares notification undo with capture undo', function () {
    $item = ($this->item)();
    app(CaptureActions::class)->execute($item);
    app(CaptureActions::class)->execute($item);
    $activity = ActivityLog::firstOrFail();
    expect($item->fresh()->status)->toBe('executed')->and($activity->source)->toBe('capture')->and($activity->minutes)->toBe(45)
        ->and($activity->occurred_at->equalTo(now()->subHour()))->toBeTrue()->and(ActivityLog::count())->toBe(1)
        ->and(FeedNotification::where('title', 'Activity logged')->count())->toBe(1);
    $this->get(route('activity.index', ['entry' => $activity->id]))->assertInertia(fn (Assert $page) => $page->has('entries.data', 1));
    $this->post(route('capture-items.undo', $item))->assertSessionHasNoErrors();
    $this->post(route('capture-items.undo', $item))->assertSessionHasNoErrors();
    expect(ActivityLog::count())->toBe(0)->and(DB::table('work_touches')->count())->toBe(0)->and($this->project->fresh()->last_touched_at)->toBeNull()
        ->and(FeedNotification::first()->undone_at)->not->toBeNull();
    app(CaptureActions::class)->execute($item);
    expect(ActivityLog::count())->toBe(0);
});

it('undoes activity without erasing later activity and protects edits to the captured entry', function () {
    $item = ($this->item)();
    app(CaptureActions::class)->execute($item);
    $this->travel(1)->hours();
    $later = ($this->item)();
    app(CaptureActions::class)->execute($later);
    $latestTouch = $this->project->fresh()->last_touched_at->toISOString();
    app(CaptureActions::class)->undo($item);
    expect($this->project->fresh()->last_touched_at->toISOString())->toBe($latestTouch)->and(ActivityLog::count())->toBe(1);
    ActivityLog::first()->update(['entry' => 'Edited later', 'revision' => 2]);
    $this->post(route('capture-items.undo', $later))->assertSessionHasErrors('undo');
    expect(ActivityLog::count())->toBe(1);
});

it('applies a wait once and restores only prior waiting fields with a monotonic revision', function () {
    $original = $this->task->fresh()->getRawOriginal();
    $item = ($this->wait)();
    app(CaptureActions::class)->execute($item);
    expect($item->fresh()->status)->toBe('executed')->and($this->task->fresh()->waiting_on_person_id)->toBe($this->person->id)
        ->and($this->task->fresh()->waiting_since->equalTo(now()->subHour()))->toBeTrue();
    expect(ActionLog::first()->before_snapshot)->toEqual($original);
    app(CaptureActions::class)->execute($item);
    expect($this->task->fresh()->wait_revision)->toBe(1)->and(ActionLog::count())->toBe(1);
    app(CaptureActions::class)->undo($item);
    expect($this->task->fresh()->waiting_on_person_id)->toBeNull()->and($this->task->fresh()->wait_revision)->toBe(2)
        ->and($this->task->fresh()->due_date->toDateString())->toBe('2026-10-20');
    expect(FeedNotification::first()->title)->toBe('Waiting on updated');
});

it('restores an existing project hand-off and protects subsequent changes', function () {
    $other = Person::create(['user_id' => $this->owner->id, 'name' => 'Jamie', 'relationship' => 'other']);
    $this->project->update(['holder' => 'other', 'holder_person_id' => $other->id, 'holder_since' => now()->subDays(3), 'wait_expected_by' => '2026-10-12']);
    $this->travel(2)->hours();
    $item = ($this->wait)(['task_ref' => null]);
    app(CaptureActions::class)->execute($item);
    expect($item->fresh()->status)->toBe('executed')->and($this->project->fresh()->holder_person_id)->toBe($this->person->id);
    app(CaptureActions::class)->undo($item);
    expect($this->project->fresh()->holder_person_id)->toBe($other->id)->and($this->project->fresh()->wait_expected_by->toDateString())->toBe('2026-10-12');
    $this->travel(2)->hours();
    $second = ($this->wait)(['task_ref' => null]);
    app(CaptureActions::class)->execute($second);
    $this->project->update(['name' => 'Later edit']);
    $this->post(route('capture-items.undo', $second))->assertSessionHasErrors('undo');
});

it('triages ambiguous fuzzy unknown and missing references without creating people or work', function (array $data) {
    $item = ($this->wait)($data);
    app(CaptureActions::class)->execute($item);
    expect($item->fresh()->status)->toBe('needs_triage')->and(ActionLog::count())->toBe(0)->and(Person::count())->toBe(1)
        ->and(Task::count())->toBe(1)->and($this->task->fresh()->waiting_on_person_id)->toBeNull();
})->with([
    [['person_ref' => 'Al']], [['person_ref' => 'Unknown']], [['task_ref' => 'Cover']],
    [['task_ref' => null, 'project_ref' => null]], [['project_ref' => 'Unknown']],
]);

it('requires a choice for duplicate exact task names and scopes by the selected project', function () {
    Task::create(['user_id' => $this->owner->id, 'domain_id' => $this->domain->id, 'project_id' => $this->project->id, 'title' => 'Cover design']);
    $item = ($this->wait)();
    app(CaptureActions::class)->execute($item);
    expect($item->fresh()->status)->toBe('needs_triage')->and($item->fresh()->candidates['tasks'])->toHaveCount(2);
    $this->put(route('capture-items.resolve', $item), ['type' => 'set_waiting', 'task_id' => $this->task->id, 'person_id' => $this->person->id, 'work_revision' => 0])->assertSessionHasNoErrors();
    expect($item->fresh()->status)->toBe('executed')->and($this->task->fresh()->waiting_on_person_id)->toBe($this->person->id);
});

it('rejects stale offline changes and stale review forms before touching work', function () {
    $item = ($this->wait)();
    $this->patch(route('tasks.waiting', $this->task), ['waiting' => true, 'revision' => 0, 'new_person_name' => 'Jamie'])->assertSessionHasNoErrors();
    app(CaptureActions::class)->execute($item);
    expect($item->fresh()->status)->toBe('needs_triage')->and($item->fresh()->error)->toContain('after the recording');
    $data = ['type' => 'set_waiting', 'task_id' => $this->task->id, 'person_id' => $this->person->id, 'work_revision' => 0];
    $this->put(route('capture-items.resolve', $item), $data)->assertSessionHasNoErrors();
    expect($item->fresh()->status)->toBe('needs_triage')->and($item->fresh()->error)->toContain('changed while');
    $this->put(route('capture-items.resolve', $item), array_replace($data, ['work_revision' => 1]))->assertSessionHasNoErrors();
    expect($item->fresh()->status)->toBe('executed');
});

it('requires review below high confidence for both new actions', function (string $type) {
    $item = $type === 'set_waiting' ? ($this->wait)(['confidence' => .79]) : ($this->item)(['confidence' => .79]);
    app(CaptureActions::class)->execute($item);
    expect($item->fresh()->status)->toBe('needs_triage')->and(ActionLog::count())->toBe(0);
})->with(['set_waiting', 'log_activity']);

it('validates extra fields duration and date values without side effects', function (array $data) {
    $item = ($this->item)($data);
    app(CaptureActions::class)->execute($item);
    expect($item->fresh()->status)->toBe('needs_triage')->and(ActivityLog::count())->toBe(0)->and(ActionLog::count())->toBe(0);
})->with([
    [['minutes' => -1]], [['minutes' => 1441]], [['due_date' => '2026-10-10']],
    [['activity_date' => '2026-02-30']], [['activity_date' => '2026-10-11']],
    [['activity_date' => '2026-03-08', 'activity_time' => '02:30']], [['unexpected' => 'value']],
]);

it('uses the capture timezone for backdated activity even after settings change', function () {
    $item = ($this->item)(['activity_date' => '2026-10-08', 'activity_time' => '20:30']);
    $this->put(route('work.timezone'), ['timezone' => 'Asia/Tokyo'])->assertSessionHasNoErrors();
    app(CaptureActions::class)->execute($item);
    expect($item->fresh()->status)->toBe('executed')->and(ActivityLog::first()->occurred_at->toISOString())->toBe('2026-10-09T01:30:00.000000Z');
});

it('excludes completed parked and foreign targets and people', function () {
    $this->task->update(['completed_at' => now()]);
    $item = ($this->wait)();
    app(CaptureActions::class)->execute($item);
    expect($item->fresh()->status)->toBe('needs_triage');
    $this->domain->update(['parked' => true]);
    $activity = ($this->item)();
    app(CaptureActions::class)->execute($activity);
    expect($activity->fresh()->status)->toBe('needs_triage');
    $other = User::factory()->create();
    $foreign = Person::create(['user_id' => $other->id, 'name' => 'Private person', 'relationship' => 'other']);
    $this->put(route('capture-items.resolve', $item), ['type' => 'set_waiting', 'project_id' => $this->project->id, 'person_id' => $foreign->id, 'work_revision' => 0])->assertSessionHasErrors('person_id');
    expect(ActionLog::count())->toBe(0);
});

it('honors the seven-day undo limit for both action types', function (string $type) {
    $item = $type === 'set_waiting' ? ($this->wait)() : ($this->item)();
    app(CaptureActions::class)->execute($item);
    expect($item->fresh()->status)->toBe('executed');
    $this->travel(8)->days();
    $this->post(route('capture-items.undo', $item))->assertSessionHasErrors('undo');
})->with(['set_waiting', 'log_activity']);

it('processes a mixed capture once with new and existing actions and truthful confirmation', function () {
    $capture = ($this->receive)('Log 45 minutes on Book. Waiting on Alex for Cover design. Buy milk.');
    $actions = [
        ['type' => 'log_activity', 'excerpt' => 'Log 45 minutes on Book.', 'body' => 'Worked on Book', 'minutes' => 45, 'project_ref' => 'Book', 'confidence' => .95],
        ['type' => 'set_waiting', 'excerpt' => 'Waiting on Alex for Cover design.', 'task_ref' => 'Cover design', 'person_ref' => 'Alex', 'confidence' => .95],
        ['type' => 'create_task', 'excerpt' => 'Buy milk.', 'title' => 'Buy milk', 'confidence' => .95],
    ];
    Http::fake(['https://api.openai.com/v1/responses' => Http::response(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['actions' => $actions])]]]]])]);
    app(CaptureProcessor::class)->process($capture->id, $this->owner->id);
    app(CaptureProcessor::class)->process($capture->id, $this->owner->id);
    expect($capture->fresh()->status)->toBe('executed')->and(ActivityLog::count())->toBe(1)->and(Task::count())->toBe(2)->and(ActionLog::count())->toBe(3);
    expect(app(CaptureService::class)->confirmation($capture)['spoken_confirmation'])->toBe('Saved. 3 filed.');
    $this->get(route('captures.show', $capture))->assertInertia(fn (Assert $page) => $page->has('options.tasks', 2)->has('capture.items', 3));
});

it('previews activity and waits with the same date and stale-work checks without writing', function (string $scenario, string $outcome) {
    $isWait = str_starts_with($scenario, 'wait');
    $text = $isWait ? 'Waiting on Alex for Cover design' : 'Log activity on Book';
    $payload = $isWait
        ? ['type' => 'set_waiting', 'task_ref' => 'Cover design', 'person_ref' => 'Alex']
        : ['type' => 'log_activity', 'project_ref' => 'Book', 'body' => 'Worked on Book'];
    if ($scenario === 'future_activity') {
        $payload['activity_date'] = now()->addDay()->toDateString();
    }
    if ($scenario === 'wait_changed') {
        $this->task->update(['title' => 'Cover design', 'updated_at' => now()]);
    }
    $payload += ['confidence' => .95, 'excerpt' => $text];
    Http::fake(['https://api.openai.com/v1/responses' => Http::response(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['actions' => [$payload]])]]]]])]);
    $this->postJson(route('parser.preview'), ['text' => $text, 'captured_at' => now()->subHour()->toISOString()])->assertOk()->assertJsonPath('items.0.outcome', $outcome);
    expect(Capture::count())->toBe(0)->and(ActivityLog::count())->toBe(0)->and(ActionLog::count())->toBe(0)->and($this->task->fresh()->waiting_on_person_id)->toBeNull();
})->with([
    ['activity', 'would_file'], ['future_activity', 'needs_triage'], ['wait', 'would_file'], ['wait_changed', 'needs_triage'],
]);
