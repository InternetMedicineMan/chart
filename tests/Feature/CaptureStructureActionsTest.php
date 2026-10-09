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
use App\Services\CaptureContext;
use App\Services\WorkSetup;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09T12:00:00Z'));
    $this->owner = User::factory()->create(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()]);
    config(['chart.owner_id' => $this->owner->id, 'inertia.ssr.enabled' => false]);
    $this->actingAs($this->owner);
    app(WorkSetup::class)->initialize($this->owner);
    $this->domain = Domain::forUser($this->owner)->where('name', 'Writing')->firstOrFail();
    $this->project = Project::create(['user_id' => $this->owner->id, 'domain_id' => $this->domain->id, 'name' => 'Book', 'slug' => 'book', 'type' => 'ongoing', 'lifecycle' => 'active']);
    $this->milestone = Milestone::create(['user_id' => $this->owner->id, 'project_id' => $this->project->id, 'title' => 'Draft', 'weight' => 3]);
    $this->task = Task::create(['user_id' => $this->owner->id, 'domain_id' => $this->domain->id, 'project_id' => $this->project->id, 'title' => 'Write chapter', 'milestone_id' => $this->milestone->id]);
    $this->travel(2)->hours();
    $this->item = function (array $data) {
        $text = $data['excerpt'] ?? 'Finish Draft in Book';
        $capture = Capture::create(['user_id' => $this->owner->id, 'raw_text' => $text, 'request_key' => Str::uuid()->toString(), 'client_captured_at' => now()->subMinute(), 'timezone' => 'America/Chicago', 'source' => 'in_app']);
        $payload = $data + ['confidence' => .95, 'excerpt' => $text];

        return CaptureItem::create(['user_id' => $this->owner->id, 'capture_id' => $capture->id, 'sequence' => 0, 'action_type' => $data['type'], 'payload' => $payload, 'excerpt' => $text, 'status' => 'pending']);
    };
});

it('completes only the milestone once and undoes without touching cadence or tasks', function () {
    $item = ($this->item)(['type' => 'complete_milestone', 'project_ref' => 'Book', 'milestone_ref' => 'Draft']);
    $actions = app(CaptureActions::class);
    $actions->execute($item);
    $actions->execute($item);
    expect($item->fresh()->status)->toBe('executed')->and(ActionLog::count())->toBe(1)
        ->and($this->milestone->fresh()->completed_at)->not->toBeNull()->and($this->task->fresh()->completed_at)->toBeNull()->and($this->project->fresh()->last_touched_at)->toBeNull();
    $actions->undo($item);
    expect($this->milestone->fresh()->completed_at)->toBeNull()->and($this->milestone->fresh()->revision)->toBe(2);
});

it('creates one child inheriting its parent and supports safe undo', function () {
    $item = ($this->item)(['type' => 'create_task', 'title' => 'Outline section', 'parent_ref' => 'Write chapter']);
    app(CaptureActions::class)->execute($item);
    app(CaptureActions::class)->execute($item);
    expect($item->fresh()->status)->toBe('executed');
    $child = Task::findOrFail($item->fresh()->target_id);
    expect($child->parent_task_id)->toBe($this->task->id)->and($child->milestone_id)->toBe($this->milestone->id)->and($child->project_id)->toBe($this->project->id);
    app(CaptureActions::class)->undo($item);
    expect($child->fresh()->trashed())->toBeTrue();
});

it('assigns a parent and deleted children and restores all on undo', function () {
    $child = Task::create(['user_id' => $this->owner->id, 'domain_id' => $this->domain->id, 'project_id' => $this->project->id, 'parent_task_id' => $this->task->id, 'milestone_id' => $this->milestone->id, 'title' => 'Child']);
    $child->delete();
    $other = Milestone::create(['user_id' => $this->owner->id, 'project_id' => $this->project->id, 'title' => 'Review']);
    $this->travel(2)->minutes();
    $item = ($this->item)(['type' => 'assign_milestone', 'task_ref' => 'Write chapter', 'milestone_ref' => 'Review']);
    app(CaptureActions::class)->execute($item);
    expect($item->fresh()->status)->toBe('executed')->and($child->fresh()->milestone_id)->toBe($other->id);
    app(CaptureActions::class)->undo($item);
    expect($this->task->fresh()->milestone_id)->toBe($this->milestone->id)->and($child->fresh()->milestone_id)->toBe($this->milestone->id);
});

it('blocks undo of assignment after any child change', function () {
    $item = ($this->item)(['type' => 'assign_milestone', 'task_ref' => 'Write chapter', 'milestone_ref' => 'Draft']);
    app(CaptureActions::class)->execute($item);
    Task::create(['user_id' => $this->owner->id, 'domain_id' => $this->domain->id, 'project_id' => $this->project->id, 'parent_task_id' => $this->task->id, 'title' => 'Later child']);
    expect(fn () => app(CaptureActions::class)->undo($item))->toThrow(ValidationException::class);
});

it('reviews uncertain references and unsupported fields without partial writes', function (array $data) {
    $item = ($this->item)($data);
    app(CaptureActions::class)->execute($item);
    expect($item->fresh()->status)->toBe('needs_triage')->and(ActionLog::count())->toBe(0)->and(Task::count())->toBe(1);
})->with([
    [['type' => 'complete_milestone', 'project_ref' => 'Book', 'milestone_ref' => 'Dra']],
    [['type' => 'complete_milestone', 'milestone_ref' => 'Draft']],
    [['type' => 'complete_milestone', 'project_ref' => 'Book', 'milestone_ref' => 'Draft', 'confidence' => .7]],
    [['type' => 'create_task', 'title' => 'Child', 'parent_ref' => 'Missing']],
    [['type' => 'assign_milestone', 'task_ref' => 'Missing', 'milestone_ref' => 'Draft']],
    [['type' => 'complete_milestone', 'project_ref' => 'Book', 'milestone_ref' => 'Draft', 'due_date' => '2026-10-09']],
]);

it('requires current revisions for review and excludes another owner', function () {
    $item = ($this->item)(['type' => 'complete_milestone', 'project_ref' => 'Book', 'milestone_ref' => 'Draft']);
    $this->milestone->update(['revision' => 1]);
    app(CaptureActions::class)->execute($item);
    expect($item->fresh()->status)->toBe('needs_triage');
    $data = ['type' => 'complete_milestone', 'project_id' => $this->project->id, 'milestone_id' => $this->milestone->id, 'milestone_revision' => 0];
    $this->put(route('capture-items.resolve', $item), $data)->assertRedirect();
    expect($item->fresh()->status)->toBe('needs_triage');
    $this->put(route('capture-items.resolve', $item), array_replace($data, ['milestone_revision' => 1]))->assertRedirect();
    expect($item->fresh()->status)->toBe('executed');
    $foreign = User::factory()->create();
    $foreignMilestone = Milestone::create(['user_id' => $foreign->id, 'project_id' => $this->project->id, 'title' => 'Foreign']);
    $this->put(route('capture-items.resolve', $item), array_replace($data, ['milestone_id' => $foreignMilestone->id]))->assertSessionHasErrors('milestone_id');
});

it('rejects nested parents, mismatched projects and inherited milestone overrides', function () {
    $child = Task::create(['user_id' => $this->owner->id, 'domain_id' => $this->domain->id, 'project_id' => $this->project->id, 'parent_task_id' => $this->task->id, 'title' => 'Child']);
    Milestone::create(['user_id' => $this->owner->id, 'project_id' => $this->project->id, 'title' => 'Review']);
    $this->travel(2)->minutes();
    foreach ([['parent_ref' => 'Child'], ['parent_ref' => 'Write chapter', 'project_ref' => 'Other'], ['parent_ref' => 'Write chapter', 'milestone_ref' => 'Review']] as $fields) {
        $item = ($this->item)(['type' => 'create_task', 'title' => 'Nested'] + $fields);
        app(CaptureActions::class)->execute($item);
        expect($item->fresh()->status)->toBe('needs_triage');
    }
    expect(Task::count())->toBe(2);
});

it('previews without writes and includes milestone and parent context', function () {
    $item = ($this->item)(['type' => 'complete_milestone', 'project_ref' => 'Book', 'milestone_ref' => 'Draft']);
    $capture = Capture::findOrFail($item->capture_id);
    expect(app(CaptureActions::class)->preview($this->owner, $capture->raw_text, $item->payload, $capture)['outcome'])->toBe('would_file');
    $context = app(CaptureContext::class)->forCapture($capture);
    expect($context['milestones'][0]['title'])->toBe('Draft')->and($context['tasks'][0])->toHaveKey('parent_task_id')->and(ActionLog::count())->toBe(0);
});

it('rejects duplicate milestone names and future recordings in execution and preview', function () {
    Milestone::create(['user_id' => $this->owner->id, 'project_id' => $this->project->id, 'title' => 'Draft']);
    $this->travel(2)->minutes();
    $item = ($this->item)(['type' => 'complete_milestone', 'project_ref' => 'Book', 'milestone_ref' => 'Draft']);
    $capture = Capture::findOrFail($item->capture_id);
    expect(app(CaptureActions::class)->preview($this->owner, $capture->raw_text, $item->payload, $capture)['outcome'])->toBe('needs_triage');
    app(CaptureActions::class)->execute($item);
    expect($item->fresh()->status)->toBe('needs_triage');
    $child = ($this->item)(['type' => 'create_task', 'title' => 'Later', 'parent_ref' => 'Write chapter']);
    Capture::find($child->capture_id)->update(['client_captured_at' => now()->addMinute()]);
    app(CaptureActions::class)->execute($child);
    expect($child->fresh()->status)->toBe('needs_triage');
});

it('reviews changed children in preview and execution before assignment', function () {
    $item = ($this->item)(['type' => 'assign_milestone', 'task_ref' => 'Write chapter', 'milestone_ref' => 'Draft']);
    Task::create(['user_id' => $this->owner->id, 'domain_id' => $this->domain->id, 'project_id' => $this->project->id, 'parent_task_id' => $this->task->id, 'title' => 'Later child']);
    $capture = Capture::findOrFail($item->capture_id);
    expect(app(CaptureActions::class)->preview($this->owner, $capture->raw_text, $item->payload, $capture)['outcome'])->toBe('needs_triage');
    app(CaptureActions::class)->execute($item);
    expect($item->fresh()->status)->toBe('needs_triage');
});

it('does not undo a later milestone edit or assign a task across projects', function () {
    $item = ($this->item)(['type' => 'complete_milestone', 'project_ref' => 'Book', 'milestone_ref' => 'Draft']);
    app(CaptureActions::class)->execute($item);
    $this->milestone->refresh()->update(['title' => 'Revised']);
    expect(fn () => app(CaptureActions::class)->undo($item))->toThrow(ValidationException::class);
    $other = Project::create(['user_id' => $this->owner->id, 'domain_id' => $this->domain->id, 'name' => 'Other', 'slug' => 'other', 'type' => 'ongoing', 'lifecycle' => 'active']);
    Milestone::create(['user_id' => $this->owner->id, 'project_id' => $other->id, 'title' => 'Launch']);
    $this->travel(2)->minutes();
    $assignment = ($this->item)(['type' => 'assign_milestone', 'task_ref' => 'Write chapter', 'milestone_ref' => 'Launch']);
    app(CaptureActions::class)->execute($assignment);
    expect($assignment->fresh()->status)->toBe('needs_triage');
});

it('creates a task directly in an existing milestone and requires a current parent for reviewed subtasks', function () {
    $item = ($this->item)(['type' => 'create_task', 'title' => 'Direct task', 'project_ref' => 'Book', 'milestone_ref' => 'Draft']);
    app(CaptureActions::class)->execute($item);
    expect($item->fresh()->status)->toBe('executed')->and(Task::find($item->fresh()->target_id)->milestone_id)->toBe($this->milestone->id);
    $child = ($this->item)(['type' => 'create_task', 'title' => 'New child', 'parent_ref' => 'Write chapter']);
    $this->task->update(['notes' => 'New work']);
    $data = ['type' => 'create_task', 'title' => 'New child', 'parent_task_id' => $this->task->id, 'parent_revision' => 0];
    $this->put(route('capture-items.resolve', $child), $data)->assertRedirect();
    expect($child->fresh()->status)->toBe('needs_triage');
    $this->put(route('capture-items.resolve', $child), array_replace($data, ['parent_revision' => $this->task->fresh()->revision]))->assertRedirect();
    expect($child->fresh()->status)->toBe('executed');
});

it('preserves a created subtask after it is selected in a later daily plan', function () {
    $item = ($this->item)(['type' => 'create_task', 'title' => 'Outline', 'parent_ref' => 'Write chapter']);
    app(CaptureActions::class)->execute($item);
    DailyPlan::create(['user_id' => $this->owner->id, 'plan_date' => '2026-10-09', 'top_task_ids' => [$item->fresh()->target_id]]);
    expect(fn () => app(CaptureActions::class)->undo($item))->toThrow(ValidationException::class);
});
