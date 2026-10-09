<?php

use App\Models\ActionLog;
use App\Models\Capture;
use App\Models\CaptureItem;
use App\Models\DailyPlan;
use App\Models\Domain;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\CaptureActions;
use App\Services\WorkSetup;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09T18:00:00Z'));
    $this->owner = User::factory()->create(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()]);
    config(['chart.owner_id' => $this->owner->id, 'inertia.ssr.enabled' => false]);
    $this->actingAs($this->owner);
    Http::preventStrayRequests();
    app(WorkSetup::class)->initialize($this->owner);
    $this->domain = Domain::forUser($this->owner)->where('name', 'Writing')->firstOrFail();
    $this->extra = Domain::forUser($this->owner)->where('name', 'Personal / Home')->firstOrFail();
    $this->project = Project::create(['user_id' => $this->owner->id, 'domain_id' => $this->domain->id, 'name' => 'Book', 'slug' => 'book', 'type' => 'ongoing', 'lifecycle' => 'active']);
    $this->milestone = Milestone::create(['user_id' => $this->owner->id, 'project_id' => $this->project->id, 'title' => 'Draft', 'weight' => 3]);
    $this->make = function (array $data = []) {
        $this->post(route('tasks.store'), $data + ['title' => 'Write article', 'project_id' => $this->project->id, 'milestone_id' => $this->milestone->id])->assertSessionHasNoErrors();

        return Task::latest('id')->firstOrFail();
    };
    $this->complete = fn (Task $task, bool $done = true) => $this->patch(route('tasks.completion', $task), ['completed' => $done, 'revision' => $task->fresh()->revision]);
    $this->milestoneData = fn (array $data = []) => $data + ['title' => 'Launch', 'weight' => 1, 'due_date' => '2026-10-20', 'sort_order' => 2, 'completed' => true, 'revision' => 0];
});

it('creates edits reopens and removes weighted milestones without completing tasks or touching cadence', function () {
    $task = ($this->make)();
    $this->post(route('milestones.store', $this->project), ($this->milestoneData)())->assertSessionHasNoErrors();
    $milestone = Milestone::latest('id')->firstOrFail();
    $this->get(route('projects.show', $this->project))->assertInertia(fn (Assert $page) => $page->has('project.milestones', 2)->where('project.milestones.0.weight', 3)->where('project.milestones.1.weight', 1));
    $this->get(route('bench'))->assertInertia(fn (Assert $page) => $page->where('projects.data.0.milestone_weight', 4)->where('projects.data.0.completed_milestone_weight', 1));
    expect($task->fresh()->completed_at)->toBeNull()->and($this->project->fresh()->last_touched_at)->toBeNull();
    $url = route('milestones.update', [$this->project, $milestone]);
    $this->put($url, ($this->milestoneData)())->assertSessionHasErrors('revision');
    $this->put($url, ($this->milestoneData)(['revision' => 1, 'completed' => false, 'weight' => 5]))->assertSessionHasNoErrors();
    expect($milestone->fresh()->completed_at)->toBeNull()->and($milestone->fresh()->weight)->toBe(5);
    $this->delete(route('milestones.destroy', [$this->project, $milestone]), ($this->milestoneData)(['revision' => 2]))->assertSessionHasNoErrors();
    expect(Milestone::count())->toBe(1);
});

it('rejects invalid milestone weights dates and stale deletion', function (array $data) {
    $this->post(route('milestones.store', $this->project), ($this->milestoneData)($data))->assertSessionHasErrors();
    expect(Milestone::count())->toBe(1);
})->with([[['weight' => 0]], [['weight' => 1001]], [['due_date' => '2026-02-30']], [['revision' => 9]]]);

it('protects milestones containing even deleted tasks and protects project deletion', function () {
    $task = ($this->make)();
    $task->delete();
    $this->delete(route('milestones.destroy', [$this->project, $this->milestone]), ($this->milestoneData)())->assertSessionHasErrors('milestone');
    $this->delete(route('projects.destroy', $this->project))->assertSessionHasErrors('project');
});

it('inherits the parent home and exposes children and milestone on the task page', function () {
    $parent = ($this->make)();
    $child = ($this->make)(['title' => 'Research', 'parent_task_id' => $parent->id, 'project_id' => null, 'domain_id' => $this->extra->id, 'milestone_id' => null]);
    expect($child->parent_task_id)->toBe($parent->id)->and($child->domain_id)->toBe($parent->domain_id)->and($child->project_id)->toBe($parent->project_id)->and($child->milestone_id)->toBe($parent->milestone_id);
    $this->get(route('tasks.show', $parent))->assertInertia(fn (Assert $page) => $page->has('subtasks.data', 1)->where('task.milestone.title', 'Draft'));
});

it('blocks nested cyclic completed and foreign parents', function (string $kind) {
    $parent = ($this->make)();
    if ($kind === 'nested') {
        $parent = ($this->make)(['parent_task_id' => $parent->id]);
    }
    if ($kind === 'completed') {
        $parent->update(['completed_at' => now()]);
    }
    if ($kind === 'foreign') {
        $parent->update(['user_id' => User::factory()->create()->id]);
    }
    if ($kind === 'self') {
        $this->put(route('tasks.update', $parent), ['title' => 'Cycle', 'project_id' => $this->project->id, 'parent_task_id' => $parent->id])->assertSessionHasErrors('parent_task_id');
    } else {
        $this->post(route('tasks.store'), ['title' => 'Child', 'parent_task_id' => $parent->id])->assertSessionHasErrors('parent_task_id');
    }
})->with(['nested', 'completed', 'foreign', 'self']);

it('requires finished subtasks and requires reopening the parent first', function () {
    $parent = ($this->make)();
    $child = ($this->make)(['parent_task_id' => $parent->id]);
    ($this->complete)($parent)->assertSessionHasErrors('completion');
    expect($parent->fresh()->completed_at)->toBeNull()->and(DB::table('work_touches')->count())->toBe(0);
    ($this->complete)($child)->assertSessionHasNoErrors();
    ($this->complete)($parent)->assertSessionHasNoErrors();
    ($this->complete)($child, false)->assertSessionHasErrors('completion');
    ($this->complete)($parent, false)->assertSessionHasNoErrors();
    ($this->complete)($child, false)->assertSessionHasNoErrors();
});

it('keeps deleted children attached during parent moves and blocks deletion of the parent', function () {
    $parent = ($this->make)();
    $child = ($this->make)(['parent_task_id' => $parent->id]);
    $child->delete();
    $this->delete(route('tasks.destroy', $parent))->assertSessionHasErrors('task');
    $this->put(route('tasks.update', $parent), ['title' => $parent->title, 'domain_id' => $this->extra->id, 'project_id' => null, 'milestone_id' => null])->assertSessionHasNoErrors();
    expect($child->fresh()->domain_id)->toBe($this->extra->id)->and($child->fresh()->project_id)->toBeNull()->and($child->fresh()->milestone_id)->toBeNull();
    $this->post(route('tasks.restore', $child))->assertSessionHasNoErrors();
});

it('rejects milestones from another project or owner', function () {
    $other = Project::create(['user_id' => $this->owner->id, 'domain_id' => $this->domain->id, 'name' => 'Other', 'slug' => 'other']);
    $this->post(route('tasks.store'), ['title' => 'Invalid', 'project_id' => $other->id, 'milestone_id' => $this->milestone->id])->assertSessionHasErrors('milestone_id');
    $this->milestone->update(['user_id' => User::factory()->create()->id]);
    $this->post(route('tasks.store'), ['title' => 'Invalid', 'project_id' => $this->project->id, 'milestone_id' => $this->milestone->id])->assertSessionHasErrors('milestone_id');
    $this->put(route('milestones.update', [$this->project, $this->milestone]), ($this->milestoneData)())->assertNotFound();
});

it('touches only owned extra targets and keeps repeat configuration', function () {
    $task = ($this->make)(['touch_target_type' => 'domain', 'touch_target_id' => $this->extra->id, 'due_date' => '2026-10-02', 'recurrence_rule' => 'FREQ=WEEKLY']);
    ($this->complete)($task)->assertSessionHasNoErrors();
    expect($this->extra->fresh()->last_touched_at->equalTo(now()))->toBeTrue()->and(DB::table('work_touches')->count())->toBe(3);
    $next = Task::where('recurrence_parent_id', $task->id)->firstOrFail();
    expect($next->touch_target_id)->toBe($this->extra->id)->and($next->milestone_id)->toBe($this->milestone->id);
    ($this->complete)($task, false)->assertSessionHasNoErrors();
    expect($this->extra->fresh()->last_touched_at)->not->toBeNull();
});

it('rejects foreign incomplete and unsupported extra touch references', function (string $kind) {
    $data = ['title' => 'Invalid', 'touch_target_type' => 'domain', 'touch_target_id' => $this->extra->id];
    if ($kind === 'foreign') {
        $this->extra->update(['user_id' => User::factory()->create()->id]);
    }
    if ($kind === 'missing') {
        $data['touch_target_id'] = null;
    }
    if ($kind === 'type') {
        $data['touch_target_type'] = 'person';
    }
    $this->post(route('tasks.store'), $data)->assertSessionHasErrors();
})->with(['foreign', 'missing', 'type']);

it('deduplicates an extra touch matching the natural target and ignores a deleted target', function () {
    $task = ($this->make)(['touch_target_type' => 'project', 'touch_target_id' => $this->project->id]);
    ($this->complete)($task)->assertSessionHasNoErrors();
    expect(DB::table('work_touches')->count())->toBe(2);
    $next = ($this->make)(['touch_target_type' => 'domain', 'touch_target_id' => $this->extra->id]);
    $this->extra->delete();
    ($this->complete)($next)->assertSessionHasNoErrors();
    expect(DB::table('work_touches')->where('subject_id', $this->extra->id)->where('subject_type', 'domain')->count())->toBe(0);
});

it('repeats a completed checklist once and can remove an untouched generated set on reopen', function () {
    $parent = ($this->make)(['due_date' => '2026-10-02', 'recurrence_rule' => 'FREQ=WEEKLY']);
    $child = ($this->make)(['title' => 'Research', 'parent_task_id' => $parent->id, 'due_date' => '2026-10-01', 'touch_target_type' => 'domain', 'touch_target_id' => $this->extra->id]);
    ($this->complete)($child)->assertSessionHasNoErrors();
    ($this->complete)($parent)->assertSessionHasNoErrors();
    ($this->complete)($parent)->assertSessionHasNoErrors();
    $next = Task::where('recurrence_parent_id', $parent->id)->firstOrFail();
    $copy = $next->subtasks()->firstOrFail();
    expect(Task::count())->toBe(4)->and($copy->due_date->toDateString())->toBe('2026-10-15')->and($copy->completed_at)->toBeNull()->and($copy->touch_target_id)->toBe($this->extra->id);
    ($this->complete)($parent, false)->assertSessionHasNoErrors();
    expect(Task::count())->toBe(2)->and($child->fresh()->completed_at)->not->toBeNull();
});

it('preserves changed planned added or deleted successor subtasks on reopen', function (string $kind) {
    $parent = ($this->make)(['due_date' => '2026-10-02', 'recurrence_rule' => 'FREQ=WEEKLY']);
    $child = ($this->make)(['parent_task_id' => $parent->id]);
    ($this->complete)($child)->assertSessionHasNoErrors();
    ($this->complete)($parent)->assertSessionHasNoErrors();
    $next = Task::where('recurrence_parent_id', $parent->id)->firstOrFail();
    $copy = $next->subtasks()->firstOrFail();
    if ($kind === 'changed') {
        $copy->update(['notes' => 'Keep this']);
    }
    if ($kind === 'deleted') {
        $copy->delete();
    }
    if ($kind === 'added') {
        ($this->make)(['parent_task_id' => $next->id]);
    }
    if ($kind === 'planned') {
        DailyPlan::create(['user_id' => $this->owner->id, 'plan_date' => '2026-10-09', 'top_task_ids' => [$copy->id]]);
    }
    ($this->complete)($parent, false)->assertSessionHasErrors('completion');
    expect($parent->fresh()->completed_at)->not->toBeNull()->and($next->fresh())->not->toBeNull();
})->with(['changed', 'deleted', 'added', 'planned']);

it('requires repeat rules on the parent rather than the child', function () {
    $parent = ($this->make)();
    $this->post(route('tasks.store'), ['title' => 'Repeating child', 'parent_task_id' => $parent->id, 'recurrence_rule' => 'FREQ=DAILY', 'due_date' => '2026-10-09'])->assertSessionHasErrors('recurrence_rule');
});

it('undoes captured extra touches without erasing later touches and triages unfinished parents', function () {
    $parent = ($this->make)(['touch_target_type' => 'domain', 'touch_target_id' => $this->extra->id]);
    $child = ($this->make)(['parent_task_id' => $parent->id]);
    $this->travel(2)->hours();
    $capture = Capture::create(['user_id' => $this->owner->id, 'request_key' => Str::uuid(), 'raw_text' => 'Mark Write article done', 'source' => 'in_app', 'mode' => 'single', 'timezone' => 'America/Chicago', 'client_captured_at' => now()->subHour(), 'status' => 'parsed']);
    $item = CaptureItem::create(['user_id' => $this->owner->id, 'capture_id' => $capture->id, 'sequence' => 0, 'action_type' => 'complete_task', 'excerpt' => $capture->raw_text, 'status' => 'pending', 'payload' => ['type' => 'complete_task', 'task_ref' => $parent->title, 'confidence' => .95, 'excerpt' => $capture->raw_text]]);
    app(CaptureActions::class)->execute($item);
    expect($item->fresh()->status)->toBe('needs_triage');
    ($this->complete)($child)->assertSessionHasNoErrors();
    $this->put(route('capture-items.resolve', $item), ['type' => 'complete_task', 'task_id' => $parent->id, 'task_revision' => $parent->fresh()->revision])->assertSessionHasNoErrors();
    expect($item->fresh()->status)->toBe('executed')->and($this->extra->fresh()->last_touched_at)->not->toBeNull();
    $other = ($this->make)(['touch_target_type' => 'domain', 'touch_target_id' => $this->extra->id]);
    ($this->complete)($other)->assertSessionHasNoErrors();
    $latest = $this->extra->fresh()->last_touched_at;
    app(CaptureActions::class)->undo($item);
    expect($this->extra->fresh()->last_touched_at->equalTo($latest))->toBeTrue()->and($parent->fresh()->completed_at)->toBeNull();
});

it('backfills legacy task capture and recurrence snapshots without invalidating undo', function () {
    $task = ($this->make)(['milestone_id' => null, 'due_date' => '2026-10-02', 'recurrence_rule' => 'FREQ=WEEKLY']);
    ($this->complete)($task)->assertSessionHasNoErrors();
    $capture = Capture::create(['user_id' => $this->owner->id, 'request_key' => Str::uuid(), 'raw_text' => 'Write article', 'source' => 'in_app', 'mode' => 'single', 'timezone' => 'America/Chicago', 'client_captured_at' => now(), 'fallback_snapshot' => $task->fresh()->getRawOriginal()]);
    $item = CaptureItem::create(['user_id' => $this->owner->id, 'capture_id' => $capture->id, 'sequence' => 0, 'action_type' => 'complete_task', 'excerpt' => $capture->raw_text, 'payload' => [], 'status' => 'executed']);
    $log = ActionLog::create(['user_id' => $this->owner->id, 'capture_id' => $capture->id, 'capture_item_id' => $item->id, 'action_type' => 'complete_task', 'target_type' => 'task', 'target_id' => $task->id, 'payload' => [], 'before_snapshot' => $task->fresh()->getRawOriginal(), 'after_snapshot' => $task->fresh()->getRawOriginal(), 'status' => 'ok', 'executed_at' => now()]);
    $migration = require database_path('migrations/2026_10_09_190627_add_milestones_and_task_structure.php');
    $snapshots = new ReflectionMethod($migration, 'snapshots');
    $snapshots->invoke($migration, false);
    expect($log->fresh()->after_snapshot)->not->toHaveKey('parent_task_id');
    $snapshots->invoke($migration, true);
    expect($log->fresh()->after_snapshot)->toEqual($task->fresh()->getRawOriginal())->and($capture->fresh()->fallback_snapshot)->toEqual($task->fresh()->getRawOriginal());
    $history = DB::table('task_completions')->where('task_id', $task->id)->first();
    expect(json_decode($history->after_snapshot, true))->toEqual($task->fresh()->getRawOriginal());
    ($this->complete)($task, false)->assertSessionHasNoErrors();
});

it('prevents capture undo from deleting a task with new subtasks', function () {
    $capture = Capture::create(['user_id' => $this->owner->id, 'request_key' => Str::uuid(), 'raw_text' => 'Write article', 'source' => 'in_app', 'mode' => 'single', 'timezone' => 'America/Chicago', 'client_captured_at' => now(), 'status' => 'parsed']);
    $item = CaptureItem::create(['user_id' => $this->owner->id, 'capture_id' => $capture->id, 'sequence' => 0, 'action_type' => 'create_task', 'excerpt' => $capture->raw_text, 'payload' => ['type' => 'create_task', 'title' => 'Write article', 'excerpt' => $capture->raw_text, 'confidence' => .95], 'status' => 'pending']);
    app(CaptureActions::class)->execute($item);
    $parent = Task::findOrFail($item->fresh()->target_id);
    ($this->make)(['parent_task_id' => $parent->id]);
    $this->post(route('capture-items.undo', $item))->assertSessionHasErrors('undo');
    expect($parent->fresh())->not->toBeNull();
});

it('blocks restoring an unfinished child under a completed parent and rejects archived touch targets', function () {
    $parent = ($this->make)();
    $child = ($this->make)(['parent_task_id' => $parent->id]);
    $child->delete();
    ($this->complete)($parent)->assertSessionHasNoErrors();
    $this->post(route('tasks.restore', $child))->assertSessionHasErrors('completion');
    $this->extra->update(['archived_at' => now()]);
    $this->post(route('tasks.store'), ['title' => 'Invalid target', 'touch_target_type' => 'domain', 'touch_target_id' => $this->extra->id])->assertSessionHasErrors('touch_target_id');
});

it('requires explicitly stopping a repeat when converting an existing task into a subtask', function () {
    $parent = ($this->make)();
    $child = ($this->make)(['title' => 'Former repeat', 'due_date' => '2026-10-09', 'recurrence_rule' => 'FREQ=DAILY']);
    $data = ['title' => $child->title, 'parent_task_id' => $parent->id, 'revision' => $child->revision];
    $this->put(route('tasks.update', $child), $data)->assertSessionHasErrors('recurrence_rule');
    $this->put(route('tasks.update', $child), $data + ['recurrence_rule' => null])->assertSessionHasNoErrors();
    expect($child->fresh()->parent_task_id)->toBe($parent->id)->and($child->fresh()->recurrence_rule)->toBeNull();
});
