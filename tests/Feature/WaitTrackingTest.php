<?php

use App\Enums\WorkHolder;
use App\Models\ActionLog;
use App\Models\Capture;
use App\Models\CaptureItem;
use App\Models\Domain;
use App\Models\Person;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\CaptureActions;
use App\Services\WaitTracking;
use App\Services\WorkSetup;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09T01:00:00Z'));
    $this->owner = User::factory()->create(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()]);
    config(['chart.owner_id' => $this->owner->id, 'inertia.ssr.enabled' => false]);
    app(WorkSetup::class)->initialize($this->owner);
    $this->actingAs($this->owner);
    Http::preventStrayRequests();
    $this->domain = Domain::forUser($this->owner)->where('name', 'Writing')->firstOrFail();
    $this->task = fn (array $attributes = []) => Task::create($attributes + ['user_id' => $this->owner->id, 'domain_id' => $this->domain->id, 'title' => 'Send a proposal']);
    $this->project = fn (array $attributes = []) => Project::create($attributes + ['user_id' => $this->owner->id, 'domain_id' => $this->domain->id, 'name' => 'Proposal', 'slug' => Str::uuid()->toString(), 'type' => 'ongoing', 'lifecycle' => 'active']);
    $this->data = fn (array $attributes = []) => $attributes + ['waiting' => true, 'revision' => 0, 'new_person_name' => 'Alex Smith', 'expected_by' => '2026-10-10'];
});

it('preserves the deadline and original wait age through response date changes and ordinary edits', function () {
    $task = ($this->task)(['due_date' => '2026-10-15']);
    $url = route('tasks.waiting', $task);
    $this->patch($url, ($this->data)())->assertSessionHasNoErrors();
    $task->refresh();
    $since = $task->waiting_since->toISOString();
    $person = $task->waiting_on_person_id;
    expect($task->wait_revision)->toBe(1)->and($task->due_date->toDateString())->toBe('2026-10-15');
    $this->travel(2)->days();
    $this->patch($url, ($this->data)(['revision' => 1, 'expected_by' => '2026-10-12', 'person_id' => $person, 'new_person_name' => null]))->assertSessionHasNoErrors();
    $this->put(route('tasks.update', $task), ['title' => 'Updated title', 'domain_id' => $this->domain->id, 'priority' => 2, 'due_date' => '2026-10-15'])->assertSessionHasNoErrors();
    expect($task->fresh()->waiting_since->toISOString())->toBe($since)->and($task->fresh()->wait_revision)->toBe(2);
    $this->patch($url, ($this->data)(['revision' => 2, 'expected_by' => '2026-10-12']))->assertSessionHasNoErrors();
    expect($task->fresh()->wait_revision)->toBe(2)->and(Person::count())->toBe(1);
    $this->patch($url, ($this->data)(['revision' => 2, 'new_person_name' => 'Jamie']))->assertSessionHasNoErrors();
    expect($task->fresh()->waiting_since->equalTo(now()))->toBeTrue()->and($task->fresh()->wait_revision)->toBe(3);
    $this->patch($url, ['waiting' => false, 'revision' => 3])->assertSessionHasNoErrors();
    expect($task->fresh()->waiting_on_person_id)->toBeNull()->and($task->fresh()->waiting_since)->toBeNull()
        ->and($task->fresh()->wait_expected_by)->toBeNull()->and($task->fresh()->completed_at)->toBeNull();
    Http::assertNothingSent();
});

it('rejects stale hand-offs and does not create their requested person', function () {
    $task = ($this->task)();
    $this->patch(route('tasks.waiting', $task), ($this->data)())->assertSessionHasNoErrors();
    $this->patch(route('tasks.waiting', $task), ($this->data)(['new_person_name' => 'Stale person']))->assertSessionHasErrors('revision');
    expect(Person::count())->toBe(1)->and($task->fresh()->wait_revision)->toBe(1);
});

it('clears completed tasks without reviving their waits when reopened', function () {
    $task = ($this->task)();
    $this->patch(route('tasks.waiting', $task), ($this->data)())->assertSessionHasNoErrors();
    $this->patch(route('tasks.completion', $task), ['completed' => true])->assertSessionHasNoErrors();
    expect($task->fresh()->wait_revision)->toBe(2)->and($task->fresh()->waiting_on_person_id)->toBeNull();
    $this->patch(route('tasks.waiting', $task), ($this->data)(['revision' => 2]))->assertSessionHasErrors('waiting');
    $this->patch(route('tasks.completion', $task), ['completed' => false])->assertSessionHasNoErrors();
    $this->patch(route('tasks.waiting', $task), ($this->data)(['revision' => 1]))->assertSessionHasErrors('revision');
    expect($task->fresh()->waiting_on_person_id)->toBeNull();
});

it('tracks project hand-offs and clears them on closing the project', function (string $lifecycle) {
    $project = ($this->project)(['target_date' => '2026-10-25']);
    $this->patch(route('projects.waiting', $project), ($this->data)())->assertSessionHasNoErrors();
    expect($project->fresh()->holder)->toBe(WorkHolder::Other)->and($project->fresh()->holder_since->equalTo(now()))->toBeTrue();
    $this->get(route('projects.show', $project))->assertInertia(fn (Assert $page) => $page->where('project.wait.person_name', 'Alex Smith')->where('project.wait.days', 0));
    $attributes = ['name' => 'Renamed', 'domain_id' => $this->domain->id, 'type' => 'target_date', 'target_date' => '2026-10-25', 'lifecycle' => $lifecycle, 'quiet_enabled' => true];
    $this->put(route('projects.update', $project), $attributes)->assertSessionHasNoErrors();
    expect($project->fresh()->holder)->toBe(WorkHolder::Me)->and($project->fresh()->wait_expected_by)->toBeNull()
        ->and($project->fresh()->target_date->toDateString())->toBe('2026-10-25');
    $this->patch(route('projects.waiting', $project), ($this->data)(['revision' => 2]))->assertSessionHasErrors('waiting');
    $this->put(route('projects.update', $project), array_replace($attributes, ['lifecycle' => 'active']))->assertSessionHasNoErrors();
    expect($project->fresh()->holder_person_id)->toBeNull();
})->with(['done', 'dropped']);

it('validates dates names and mutually exclusive person choices', function () {
    $task = ($this->task)();
    $person = Person::create(['user_id' => $this->owner->id, 'name' => 'Existing', 'relationship' => 'other']);
    foreach ([
        [['new_person_name' => '   '], 'person_id'],
        [['new_person_name' => str_repeat('a', 101)], 'new_person_name'],
        [['expected_by' => '2026-02-30'], 'expected_by'],
        [['person_id' => $person->id], 'person_id'],
        [['waiting' => false], 'new_person_name'],
    ] as [$data, $error]) {
        $this->patch(route('tasks.waiting', $task), ($this->data)($data))->assertSessionHasErrors($error);
    }
    expect($task->fresh()->wait_revision)->toBe(0)->and(Person::count())->toBe(1);
});

it('reuses normalized names but asks to disambiguate duplicate people', function () {
    $task = ($this->task)();
    $person = Person::create(['user_id' => $this->owner->id, 'name' => 'Alex Smith', 'relationship' => 'other']);
    $this->patch(route('tasks.waiting', $task), ($this->data)(['new_person_name' => '  alex   SMITH  ']))->assertSessionHasNoErrors();
    expect($task->fresh()->waiting_on_person_id)->toBe($person->id)->and(Person::count())->toBe(1);
    Person::create(['user_id' => $this->owner->id, 'name' => 'Alex Smith', 'relationship' => 'other']);
    $this->patch(route('tasks.waiting', $task), ($this->data)(['revision' => 1]))->assertSessionHasErrors('new_person_name');
    $this->patch(route('tasks.waiting', $task), ($this->data)(['revision' => 1, 'person_id' => $person->id, 'new_person_name' => null]))->assertSessionHasNoErrors();
});

it('enforces ownership deleted-person constraints and confirmed two factor', function () {
    $task = ($this->task)();
    $other = User::factory()->create();
    $person = Person::create(['user_id' => $other->id, 'name' => 'Private name', 'relationship' => 'other']);
    $this->patch(route('tasks.waiting', $task), ($this->data)(['person_id' => $person->id, 'new_person_name' => null]))->assertSessionHasErrors('person_id');
    $person->update(['user_id' => $this->owner->id]);
    $person->delete();
    $this->patch(route('tasks.waiting', $task), ($this->data)(['person_id' => $person->id, 'new_person_name' => null]))->assertSessionHasErrors('person_id');
    $foreignTask = ($this->task)(['user_id' => $other->id]);
    $foreignProject = ($this->project)(['user_id' => $other->id]);
    $this->patch(route('tasks.waiting', $foreignTask), ($this->data)())->assertNotFound();
    $this->patch(route('projects.waiting', $foreignProject), ($this->data)())->assertNotFound();
    $this->owner->update(['two_factor_confirmed_at' => null]);
    $this->patchJson(route('tasks.waiting', $task), ($this->data)())->assertForbidden();
    $this->actingAs($other);
    $this->patchJson(route('tasks.waiting', $task), ($this->data)())->assertForbidden();
    auth()->forgetGuards();
    $this->patchJson(route('tasks.waiting', $task), ($this->data)())->assertUnauthorized();
    expect($task->fresh()->wait_revision)->toBe(0);
});

it('caps the briefing prioritizes overdue waits and excludes inactive work', function () {
    $person = Person::create(['user_id' => $this->owner->id, 'name' => 'Alex', 'relationship' => 'other']);
    $waiting = ['waiting_on_person_id' => $person->id, 'waiting_since' => now()->subDays(3), 'due_date' => '2026-10-07'];
    foreach (range(1, 6) as $number) {
        ($this->task)($waiting + ['wait_expected_by' => '2026-10-10']);
    }
    $project = ($this->project)(['holder' => 'other', 'holder_person_id' => $person->id, 'holder_since' => now()->subDays(2), 'wait_expected_by' => '2026-10-07']);
    ($this->task)($waiting + ['completed_at' => now()]);
    $someday = ($this->project)(['lifecycle' => 'someday', 'holder' => 'other', 'holder_person_id' => $person->id]);
    ($this->task)($waiting + ['project_id' => $someday->id]);
    $parked = Domain::forUser($this->owner)->where('is_inbox', false)->where('id', '!=', $this->domain->id)->firstOrFail();
    $parked->update(['parked' => true]);
    ($this->task)($waiting + ['domain_id' => $parked->id]);
    ($this->task)(['due_date' => '2026-10-08']);
    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('waiting.total', 7)->has('waiting.items', 5)
        ->where('waiting.items.0.type', 'project')->where('waiting.items.0.id', $project->id)->where('waiting.items.0.wait.overdue', true)->where('dueCount', 1));
    $this->get('/bench?status=waiting&project_status=waiting')->assertInertia(fn (Assert $page) => $page->where('tasks.total', 6)->where('projects.total', 1)->where('tasks.data.0.wait.person_name', 'Alex'));
});

it('uses local calendar days for wait age and response deadlines across daylight saving', function (string $start, string $end, int $days, string $expected, bool $overdue) {
    $person = Person::create(['user_id' => $this->owner->id, 'name' => 'Alex', 'relationship' => 'other']);
    $task = ($this->task)(['waiting_on_person_id' => $person->id, 'waiting_since' => CarbonImmutable::parse($start), 'wait_expected_by' => $expected]);
    $this->travelTo(CarbonImmutable::parse($end));
    $wait = app(WaitTracking::class)->decorate($task->newCollection([$task]), $this->owner)->first()->wait;
    expect($wait['days'])->toBe($days)->and($wait['overdue'])->toBe($overdue);
})->with([
    ['2026-10-08T23:30:00Z', '2026-10-09T01:00:00Z', 0, '2026-10-08', false],
    ['2026-10-08T23:30:00Z', '2026-10-09T05:00:00Z', 1, '2026-10-08', true],
    ['2026-03-08T05:30:00Z', '2026-03-09T05:00:00Z', 2, '2026-03-08', true],
    ['2026-11-01T04:30:00Z', '2026-11-02T06:00:00Z', 2, '2026-11-02', false],
]);

it('keeps a handed-off Top 3 selection visible but excludes waits from new picks', function () {
    $task = ($this->task)();
    $plan = ['plan_date' => '2026-10-08', 'revision' => 0, 'top_task_ids' => [$task->id], 'tomorrow_focus' => null];
    $this->put('/daily-plan', $plan)->assertSessionHasNoErrors();
    $this->patch(route('tasks.waiting', $task), ($this->data)())->assertSessionHasNoErrors();
    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('dailyPlan.tasks.0.wait.person_name', 'Alex Smith'));
    $this->getJson('/daily-plan/tasks')->assertJsonPath('total', 0);
    $this->put('/daily-plan', array_replace($plan, ['revision' => 1, 'tomorrow_focus' => 'Follow up']))->assertSessionHasNoErrors();
    $another = ($this->task)();
    $this->patch(route('tasks.waiting', $another), ($this->data)())->assertSessionHasNoErrors();
    $this->put('/daily-plan', array_replace($plan, ['revision' => 2, 'top_task_ids' => [$another->id]]))->assertSessionHasErrors('top_task_ids');
    $this->patch(route('tasks.waiting', $task), ['waiting' => false, 'revision' => 1])->assertSessionHasNoErrors();
    $this->getJson('/daily-plan/tasks')->assertJsonPath('total', 1);
});

it('upgrades old capture snapshots so undo and fallback comparisons still work', function () {
    $migration = require database_path('migrations/2026_10_09_025158_add_wait_tracking_to_work_records.php');
    $migration->down();
    Bus::fake();
    config(['chart.capture.enabled' => true, 'chart.capture.key' => 'test-key']);
    $items = [];
    foreach (['create_task', 'create_project'] as $type) {
        $this->postJson('/captures', ['text' => 'Proposal', 'request_key' => Str::uuid()->toString(), 'captured_at' => now()->toISOString(), 'owner_id' => $this->owner->id])->assertAccepted();
        $capture = Capture::latest('id')->firstOrFail();
        $item = CaptureItem::create(['user_id' => $this->owner->id, 'capture_id' => $capture->id, 'sequence' => 0, 'excerpt' => 'Proposal', 'action_type' => $type, 'payload' => ['type' => $type, 'confidence' => .95, 'excerpt' => 'Proposal', 'title' => 'Proposal'], 'status' => 'pending']);
        app(CaptureActions::class)->execute($item);
        expect($item->fresh()->status)->toBe('executed');
        $items[] = $item;
    }
    $fallback = ($this->task)();
    $capture->update(['fallback_task_id' => $fallback->id, 'fallback_snapshot' => $fallback->fresh()->getRawOriginal()]);
    $migration->up();
    expect($capture->fresh()->fallback_snapshot)->toEqual($fallback->fresh()->getRawOriginal());
    foreach ($items as $item) {
        app(CaptureActions::class)->undo($item);
        expect($item->fresh()->status)->toBe('undone');
    }
    expect(ActionLog::where('status', 'undone')->count())->toBe(2);
});
