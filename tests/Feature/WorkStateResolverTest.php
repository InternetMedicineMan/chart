<?php

use App\Models\AppSetting;
use App\Models\Domain;
use App\Models\Person;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\WorkSetup;
use App\Services\WorkStateResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-10T01:00:00Z'));
    $this->owner = User::factory()->create(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()]);
    config(['chart.owner_id' => $this->owner->id, 'inertia.ssr.enabled' => false]);
    $this->actingAs($this->owner);
    app(WorkSetup::class)->initialize($this->owner);
    Http::preventStrayRequests();
    $this->domain = Domain::forUser($this->owner)->where('name', 'Writing')->firstOrFail();
    $this->project = fn (array $attributes = []) => Project::create($attributes + ['user_id' => $this->owner->id, 'domain_id' => $this->domain->id, 'name' => 'Proposal', 'slug' => Str::uuid()->toString(), 'type' => 'ongoing', 'lifecycle' => 'active']);
    $this->task = fn (Project $project, array $attributes = []) => Task::create($attributes + ['user_id' => $this->owner->id, 'domain_id' => $project->domain_id, 'project_id' => $project->id, 'title' => 'Send proposal']);
    $this->person = Person::create(['user_id' => $this->owner->id, 'name' => 'Alex', 'relationship' => 'other']);
    $this->snapshot = fn () => app(WorkStateResolver::class)->snapshot($this->owner);
    $this->state = fn (Project $project) => ($this->snapshot)()['projects']->get($project->id);
});

it('applies quiet then my move then waiting precedence with matching parent urgency', function () {
    $project = ($this->project)(['cadence_days' => 7, 'last_touched_at' => now()->subDays(9)]);
    $due = ($this->task)($project, ['due_date' => '2026-10-09']);
    $wait = ($this->task)($project, ['waiting_on_person_id' => $this->person->id, 'waiting_since' => now()->subDays(2), 'wait_expected_by' => '2026-10-12']);
    expect(($this->state)($project)['state'])->toBe('quiet');
    expect(($this->snapshot)()['domains'][$this->domain->id]['state'])->toBe('quiet');
    $project->update(['last_touched_at' => now()]);
    expect(($this->state)($project)['state'])->toBe('my_move');
    $due->update(['due_date' => '2026-10-15']);
    expect(($this->state)($project)['state'])->toBe('waiting');
    $wait->update(['wait_expected_by' => '2026-10-08']);
    $state = ($this->state)($project);
    expect($state['state'])->toBe('quiet')->and($state['reason'])->toContain('Wait overdue: Alex')->and($state['holder']['overdue_days'])->toBe(1);
    $wait->delete();
    expect(($this->state)($project)['state'])->toBe('ok');
    Http::assertNothingSent();
});

it('keeps no-cadence work calm and respects the quiet switch without hiding overdue waits', function () {
    $project = ($this->project)(['last_touched_at' => now()->subDays(300)]);
    expect(($this->state)($project)['state'])->toBe('ok');
    $project->update(['cadence_days' => 7, 'quiet_enabled' => false]);
    expect(($this->state)($project)['state'])->toBe('ok');
    $project->update(['holder' => 'other', 'holder_person_id' => $this->person->id, 'holder_since' => now(), 'wait_expected_by' => '2026-10-08']);
    expect(($this->state)($project)['state'])->toBe('quiet');
});

it('uses creation as the cadence baseline without claiming a touch happened', function () {
    $project = ($this->project)(['cadence_days' => 7, 'created_at' => now()->subDays(7)]);
    expect(($this->state)($project)['state'])->toBe('ok');
    $this->travel(1)->days();
    $state = ($this->state)($project);
    expect($state['state'])->toBe('quiet')->and($state['recency'])->toBe('No activity yet')
        ->and($state['days_since_touch'])->toBeNull()->and($state['reason'])->toContain('8 days without activity');
});

it('excludes non-active projects and their tasks from parent attention', function (string $lifecycle) {
    $project = ($this->project)(['lifecycle' => $lifecycle, 'cadence_days' => 1, 'created_at' => now()->subDays(20), 'holder' => 'other', 'holder_person_id' => $this->person->id, 'wait_expected_by' => '2026-10-01']);
    ($this->task)($project, ['due_date' => '2026-10-01', 'waiting_on_person_id' => $this->person->id, 'wait_expected_by' => '2026-10-01']);
    $snapshot = ($this->snapshot)();
    expect($snapshot['projects'][$project->id]['state'])->toBe('excluded')->and($snapshot['domains'][$this->domain->id]['state'])->toBe('ok');
    expect(app(WorkStateResolver::class)->quiet($snapshot)['total'])->toBe(0);
})->with(['parked', 'someday', 'done', 'dropped']);

it('excludes parked archived and deleted domains and projects', function () {
    $project = ($this->project)(['cadence_days' => 1, 'created_at' => now()->subDays(20)]);
    ($this->task)($project, ['due_date' => '2026-10-01']);
    foreach ([['parked' => true], ['parked' => false, 'archived_at' => now()]] as $attributes) {
        $this->domain->update($attributes);
        expect(($this->state)($project)['state'])->toBe('excluded')->and(($this->snapshot)()['domains'][$this->domain->id]['state'])->toBe('excluded');
    }
    $this->domain->update(['archived_at' => null]);
    $project->delete();
    expect(($this->snapshot)()['projects']->has($project->id))->toBeFalse()->and(($this->snapshot)()['domains'][$this->domain->id]['counts']['open_count'])->toBe(0);
});

it('only marks finite outcomes with open work as my move', function () {
    $project = ($this->project)(['type' => 'target_date', 'target_date' => '2026-10-15']);
    expect(($this->state)($project)['state'])->toBe('ok');
    $task = ($this->task)($project, ['due_date' => '2026-10-20']);
    expect(($this->state)($project)['state'])->toBe('my_move');
    $task->update(['waiting_on_person_id' => $this->person->id, 'waiting_since' => now()]);
    expect(($this->state)($project)['state'])->toBe('waiting');
    $task->update(['completed_at' => now()]);
    expect(($this->state)($project)['state'])->toBe('ok');
});

it('uses current local Top 3 without duplicating its tasks in the due block', function () {
    $project = ($this->project)();
    $task = ($this->task)($project);
    $plan = ['plan_date' => '2026-10-09', 'revision' => 0, 'top_task_ids' => [$task->id], 'tomorrow_focus' => null];
    $this->put('/daily-plan', $plan)->assertSessionHasNoErrors();
    expect(($this->state)($project)['state'])->toBe('my_move');
    $task->update(['due_date' => '2026-10-09']);
    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->has('dailyPlan.tasks', 1)->has('dueTasks', 0)->where('dueCount', 0));
    $task->update(['due_date' => null]);
    $this->travelTo(CarbonImmutable::parse('2026-10-10T05:00:00Z'));
    expect(($this->state)($project)['state'])->toBe('ok');
});

it('recomputes after task completion reopening waits and domain changes', function () {
    $project = ($this->project)(['cadence_days' => 7, 'last_touched_at' => now()->subDays(10)]);
    $task = ($this->task)($project, ['due_date' => '2026-10-09']);
    expect(($this->state)($project)['state'])->toBe('quiet');
    $this->patch(route('tasks.completion', $task), ['completed' => true])->assertSessionHasNoErrors();
    expect(($this->state)($project)['state'])->toBe('ok')->and(($this->state)($project)['recency'])->toBe('Active today');
    $this->patch(route('tasks.completion', $task), ['completed' => false])->assertSessionHasNoErrors();
    expect(($this->state)($project)['state'])->toBe('my_move');
    $this->patch(route('tasks.waiting', $task), ['waiting' => true, 'revision' => 0, 'person_id' => $this->person->id])->assertSessionHasNoErrors();
    expect(($this->state)($project)['state'])->toBe('waiting');
    $this->patch(route('tasks.waiting', $task), ['waiting' => false, 'revision' => 1])->assertSessionHasNoErrors();
    expect(($this->state)($project)['state'])->toBe('my_move');
    $this->domain->update(['parked' => true]);
    expect(($this->state)($project)['state'])->toBe('excluded');
    $this->domain->update(['parked' => false]);
    expect(($this->state)($project)['state'])->toBe('my_move');
});

it('uses local dates at Chicago evenings and both DST transitions', function (string $start, string $end, int $days) {
    $project = ($this->project)(['cadence_days' => 1, 'last_touched_at' => CarbonImmutable::parse($start)]);
    $this->travelTo(CarbonImmutable::parse($end));
    $state = ($this->state)($project);
    expect($state['days_since_touch'])->toBe($days)->and($state['state'])->toBe($days > 1 ? 'quiet' : 'ok');
})->with([
    ['2026-10-09T23:00:00Z', '2026-10-10T01:00:00Z', 0],
    ['2026-03-08T05:30:00Z', '2026-03-09T05:00:00Z', 2],
    ['2026-11-01T04:30:00Z', '2026-11-02T06:00:00Z', 2],
]);

it('updates overdue wait state when the timezone or local day changes', function () {
    $project = ($this->project)(['holder' => 'other', 'holder_person_id' => $this->person->id, 'holder_since' => now(), 'wait_expected_by' => '2026-10-09']);
    expect(($this->state)($project)['state'])->toBe('waiting');
    AppSetting::forUser($this->owner)->where('key', 'timezone')->update(['value' => json_encode('Asia/Tokyo')]);
    expect(($this->state)($project)['state'])->toBe('quiet')->and(($this->state)($project)['holder']['overdue_days'])->toBe(1);
});

it('shows identical project state on Briefing Bench and the project page with no duplicate roll-up', function () {
    $project = ($this->project)(['holder' => 'other', 'holder_person_id' => $this->person->id, 'holder_since' => now()->subDays(3), 'wait_expected_by' => '2026-10-08']);
    $state = ($this->state)($project);
    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('quiet.total', 1)->where('quiet.items.0.id', $project->id)->where('quiet.items.0.work_state', $state));
    $this->get('/bench?state=quiet')->assertInertia(fn (Assert $page) => $page->where('projects.total', 1)->where('projects.data.0.work_state', $state));
    $this->get(route('projects.show', $project))->assertInertia(fn (Assert $page) => $page->where('project.work_state', $state));
});

it('paginates state filtered projects and caps the quiet block without suppressing quiet domains', function () {
    $this->domain->update(['last_touched_at' => now()->subDays(20)]);
    foreach (range(1, 14) as $number) {
        ($this->project)(['name' => 'Quiet '.$number, 'cadence_days' => 7, 'last_touched_at' => now()->subDays(9)]);
    }
    ($this->project)(['name' => 'Calm']);
    $this->get('/bench?state=quiet')->assertInertia(fn (Assert $page) => $page->where('projects.total', 14)->has('projects.data', 12));
    $this->get('/bench?state=quiet&projects_page=2')->assertInertia(fn (Assert $page) => $page->has('projects.data', 2));
    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('quiet.total', 15)->has('quiet.items', 5)->where('quiet.items.0.type', 'domain'));
    $this->get('/bench?state=bogus')->assertSessionHasErrors('state');
});

it('keeps owner data isolated and uses a fixed query count as projects grow', function () {
    $project = ($this->project)();
    ($this->task)($project, ['due_date' => '2026-10-09']);
    $other = User::factory()->create();
    $foreign = ($this->project)(['user_id' => $other->id, 'cadence_days' => 1, 'created_at' => now()->subDays(20)]);
    $person = Person::create(['user_id' => $other->id, 'name' => 'Private name', 'relationship' => 'other']);
    $project->update(['holder' => 'other', 'holder_person_id' => $person->id]);
    DB::enableQueryLog();
    $snapshot = ($this->snapshot)();
    $count = count(DB::getQueryLog());
    expect($snapshot['projects']->has($foreign->id))->toBeFalse()->and($snapshot['projects'][$project->id]['oldest_wait']['person_name'])->toBe('Person unavailable');
    foreach (range(1, 20) as $number) {
        ($this->task)(($this->project)(), ['due_date' => '2026-10-09']);
    }
    DB::flushQueryLog();
    ($this->snapshot)();
    expect(count(DB::getQueryLog()))->toBe($count)->and($count)->toBeLessThanOrEqual(8);
    DB::disableQueryLog();
    Http::assertNothingSent();
});
