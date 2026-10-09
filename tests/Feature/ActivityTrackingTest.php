<?php

use App\Models\ActivityLog;
use App\Models\Domain;
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
    $this->travelTo(CarbonImmutable::parse('2026-10-09T18:30:00Z'));
    $this->owner = User::factory()->create(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()]);
    config(['chart.owner_id' => $this->owner->id, 'inertia.ssr.enabled' => false]);
    $this->actingAs($this->owner);
    app(WorkSetup::class)->initialize($this->owner);
    Http::preventStrayRequests();
    $this->domain = Domain::forUser($this->owner)->where('name', 'Writing')->firstOrFail();
    $this->project = Project::create(['user_id' => $this->owner->id, 'domain_id' => $this->domain->id, 'name' => 'Book', 'slug' => 'book', 'type' => 'ongoing', 'lifecycle' => 'active', 'cadence_days' => 7, 'created_at' => now()->subDays(20)]);
    $this->data = fn (array $attributes = []) => $attributes + ['subject_type' => 'project', 'subject_id' => $this->project->id, 'entry' => 'Drafted the introduction', 'minutes' => 45, 'occurred_local' => '2026-10-09T13:00', 'timezone' => 'America/Chicago', 'request_key' => Str::uuid()->toString(), 'revision' => 0];
});

it('logs activity atomically with touches and monthly totals for project and domain', function () {
    expect(app(WorkStateResolver::class)->snapshot($this->owner)['projects'][$this->project->id]['state'])->toBe('quiet');
    $this->post('/activity', ($this->data)())->assertSessionHasNoErrors();
    $record = ActivityLog::firstOrFail();
    expect($record->occurred_at->toISOString())->toBe('2026-10-09T18:00:00.000000Z')->and($record->source)->toBe('manual')
        ->and($record->revision)->toBe(1)->and($record->domain_id)->toBe($this->domain->id);
    expect($this->project->fresh()->last_touched_at->toISOString())->toBe($record->occurred_at->toISOString())
        ->and($this->domain->fresh()->last_touched_at->toISOString())->toBe($record->occurred_at->toISOString());
    $snapshot = app(WorkStateResolver::class)->snapshot($this->owner);
    expect($snapshot['projects'][$this->project->id]['state'])->toBe('ok')->and($snapshot['projects'][$this->project->id]['minutes_month'])->toBe(45)
        ->and($snapshot['domains'][$this->domain->id]['minutes_month'])->toBe(45)->and(DB::table('work_touches')->count())->toBe(2);
    $this->get(route('projects.show', $this->project))->assertInertia(fn (Assert $page) => $page->has('activity.data', 1)->where('project.work_state.minutes_month', 45));
    Http::assertNothingSent();
});

it('deduplicates retries and refuses changed reuse of an old request key', function () {
    $data = ($this->data)();
    $this->post('/activity', $data)->assertSessionHasNoErrors();
    $this->post('/activity', $data)->assertSessionHasNoErrors();
    $this->post('/activity', array_replace($data, ['entry' => 'Different']))->assertSessionHasErrors('request_key');
    expect(ActivityLog::count())->toBe(1)->and(DB::table('work_touches')->count())->toBe(2);
    $this->delete(route('activity.destroy', ActivityLog::first()), ['revision' => 1])->assertSessionHasNoErrors();
    $this->post('/activity', $data)->assertSessionHasErrors('request_key');
    expect(ActivityLog::count())->toBe(0);
});

it('preserves a later touch when adding or correcting backdated activity', function () {
    $first = ($this->data)();
    $this->post('/activity', $first)->assertSessionHasNoErrors();
    $this->post('/activity', ($this->data)(['occurred_local' => '2026-10-01T10:00', 'minutes' => null]))->assertSessionHasNoErrors();
    $older = ActivityLog::latest('id')->first();
    expect($this->project->fresh()->last_touched_at->toISOString())->toBe('2026-10-09T18:00:00.000000Z');
    $this->put(route('activity.update', $older), ($this->data)(['request_key' => $older->request_key, 'revision' => 1, 'occurred_local' => '2026-10-02T10:00', 'minutes' => 20]))->assertSessionHasNoErrors();
    expect($this->project->fresh()->last_touched_at->toISOString())->toBe('2026-10-09T18:00:00.000000Z');
    $newest = ActivityLog::first();
    $this->delete(route('activity.destroy', $newest), ['revision' => 1])->assertSessionHasNoErrors();
    expect($this->project->fresh()->last_touched_at->toISOString())->toBe('2026-10-02T15:00:00.000000Z');
    $this->delete(route('activity.destroy', $older), ['revision' => 2])->assertSessionHasNoErrors();
    expect($this->project->fresh()->last_touched_at)->toBeNull()->and($this->domain->fresh()->last_touched_at)->toBeNull();
    expect(app(WorkStateResolver::class)->snapshot($this->owner)['projects'][$this->project->id]['state'])->toBe('quiet');
});

it('rejects stale edits removals and attempts to change the subject', function () {
    $data = ($this->data)();
    $this->post('/activity', $data)->assertSessionHasNoErrors();
    $record = ActivityLog::first();
    $this->put(route('activity.update', $record), array_replace($data, ['revision' => 1, 'entry' => 'Revised']))->assertSessionHasNoErrors();
    $this->put(route('activity.update', $record), array_replace($data, ['revision' => 1]))->assertSessionHasErrors('revision');
    $this->delete(route('activity.destroy', $record), ['revision' => 1])->assertSessionHasErrors('revision');
    $this->put(route('activity.update', $record), array_replace($data, ['revision' => 2, 'subject_type' => 'domain', 'subject_id' => $this->domain->id]))->assertSessionHasErrors('subject_id');
    expect($record->fresh()->entry)->toBe('Revised')->and($record->fresh()->revision)->toBe(2);
});

it('keeps domain history with its original domain after a project moves', function () {
    $data = ($this->data)();
    $this->post('/activity', $data)->assertSessionHasNoErrors();
    $record = ActivityLog::first();
    $otherDomain = Domain::forUser($this->owner)->where('name', 'Family')->firstOrFail();
    $this->put(route('projects.update', $this->project), ['name' => 'Book', 'domain_id' => $otherDomain->id, 'type' => 'ongoing', 'lifecycle' => 'active', 'quiet_enabled' => true])->assertSessionHasNoErrors();
    $this->put(route('activity.update', $record), array_replace($data, ['revision' => 1, 'minutes' => 60]))->assertSessionHasNoErrors();
    $this->post('/activity', ($this->data)(['minutes' => 15]))->assertSessionHasNoErrors();
    $snapshot = app(WorkStateResolver::class)->snapshot($this->owner);
    expect($snapshot['projects'][$this->project->id]['minutes_month'])->toBe(75)->and($snapshot['domains'][$this->domain->id]['minutes_month'])->toBe(60)
        ->and($snapshot['domains'][$otherDomain->id]['minutes_month'])->toBe(15);
    $this->get(route('activity.index', ['subject_type' => 'domain', 'subject_id' => $this->domain->id]))->assertInertia(fn (Assert $page) => $page->where('entries.total', 1));
});

it('preserves task-completion touches after activity removal and does not duplicate repeated completion', function () {
    $task = Task::create(['user_id' => $this->owner->id, 'domain_id' => $this->domain->id, 'project_id' => $this->project->id, 'title' => 'Review']);
    $this->patch(route('tasks.completion', $task), ['completed' => true])->assertSessionHasNoErrors();
    $this->patch(route('tasks.completion', $task), ['completed' => true])->assertSessionHasNoErrors();
    expect(DB::table('work_touches')->count())->toBe(2);
    $this->travel(1)->hours();
    $this->post('/activity', ($this->data)(['occurred_local' => '2026-10-09T14:00']))->assertSessionHasNoErrors();
    $this->delete(route('activity.destroy', ActivityLog::first()), ['revision' => 1])->assertSessionHasNoErrors();
    expect($this->project->fresh()->last_touched_at->toISOString())->toBe('2026-10-09T18:30:00.000000Z');
    $this->patch(route('tasks.completion', $task), ['completed' => false])->assertSessionHasNoErrors();
    expect($this->project->fresh()->last_touched_at->toISOString())->toBe('2026-10-09T18:30:00.000000Z');
    $this->patch(route('tasks.completion', $task), ['completed' => true])->assertSessionHasNoErrors();
    expect($this->project->fresh()->last_touched_at->equalTo(now()))->toBeTrue()->and(DB::table('work_touches')->count())->toBe(2);
});

it('preserves pre-upgrade touch dates as baselines when activity is removed', function () {
    $migration = require database_path('migrations/2026_10_09_124537_create_activity_logs_and_work_touches.php');
    $migration->down();
    $this->project->update(['last_touched_at' => now()->subDays(4)]);
    $this->domain->update(['last_touched_at' => now()->subDays(2)]);
    $migration->up();
    $this->post('/activity', ($this->data)())->assertSessionHasNoErrors();
    $this->delete(route('activity.destroy', ActivityLog::first()), ['revision' => 1])->assertSessionHasNoErrors();
    expect($this->project->fresh()->last_touched_at->equalTo(now()->subDays(4)))->toBeTrue()
        ->and($this->domain->fresh()->last_touched_at->equalTo(now()->subDays(2)))->toBeTrue();
});

it('validates empty notes duration impossible times future dates and stale timezone', function () {
    foreach ([['entry' => '   '], ['entry' => str_repeat('a', 10001)], ['minutes' => 0], ['minutes' => 1441], ['minutes' => 1.5], ['subject_type' => 'person'], ['occurred_local' => '2026-10-10T13:00'], ['occurred_local' => '2026-03-08T02:30'], ['occurred_local' => '2026-02-30T09:00'], ['timezone' => 'Asia/Tokyo'], ['request_key' => 'no']] as $data) {
        $this->post('/activity', ($this->data)($data))->assertSessionHasErrors(array_key_first($data));
    }
    expect(ActivityLog::count())->toBe(0)->and(DB::table('work_touches')->count())->toBe(0);
});

it('counts a month using local boundaries instead of UTC dates', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02T18:00:00Z'));
    $this->post('/activity', ($this->data)(['occurred_local' => '2026-10-31T23:30', 'minutes' => 50]))->assertSessionHasNoErrors();
    $this->post('/activity', ($this->data)(['occurred_local' => '2026-11-01T02:30', 'minutes' => 25]))->assertSessionHasNoErrors();
    expect(ActivityLog::first()->occurred_at->toISOString())->toBe('2026-11-01T04:30:00.000000Z');
    expect(app(WorkStateResolver::class)->snapshot($this->owner)['projects'][$this->project->id]['minutes_month'])->toBe(25);
});

it('scopes writes history and deletion to the owner with confirmed two factor', function () {
    $foreignUser = User::factory()->create();
    $foreign = ActivityLog::factory()->create(['user_id' => $foreignUser->id]);
    $this->post('/activity', ($this->data)(['subject_type' => 'domain', 'subject_id' => $foreign->domain_id]))->assertNotFound();
    $this->put(route('activity.update', $foreign), ($this->data)())->assertNotFound();
    $this->delete(route('activity.destroy', $foreign), ['revision' => 1])->assertNotFound();
    $this->get('/activity')->assertInertia(fn (Assert $page) => $page->where('entries.total', 0));
    $this->get(route('activity.index', ['subject_type' => 'domain', 'subject_id' => $foreign->domain_id]))->assertNotFound();
    $this->owner->update(['two_factor_confirmed_at' => null]);
    $this->postJson('/activity', ($this->data)())->assertForbidden();
    $this->getJson('/activity')->assertForbidden();
    auth()->forgetGuards();
    $this->postJson('/activity', ($this->data)())->assertUnauthorized();
    $this->getJson('/activity')->assertUnauthorized();
});

it('paginates history and prevents deleting subjects with active activity history', function () {
    ActivityLog::factory()->count(22)->create(['user_id' => $this->owner->id, 'subject_type' => 'project', 'subject_id' => $this->project->id, 'domain_id' => $this->domain->id]);
    $this->get('/activity')->assertInertia(fn (Assert $page) => $page->where('entries.total', 22)->has('entries.data', 20));
    $this->get('/activity?page=2')->assertInertia(fn (Assert $page) => $page->has('entries.data', 2));
    $this->delete(route('projects.destroy', $this->project))->assertSessionHasErrors('project');
    $empty = Domain::create(['user_id' => $this->owner->id, 'name' => 'Direct activity', 'slug' => 'direct']);
    $this->post('/activity', ($this->data)(['subject_type' => 'domain', 'subject_id' => $empty->id]))->assertSessionHasNoErrors();
    $this->delete(route('domains.destroy', $empty))->assertSessionHasErrors('domain');
});
