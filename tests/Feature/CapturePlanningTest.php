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
use App\Services\CaptureProcessor;
use App\Services\CaptureService;
use App\Services\DailyPlanning;
use App\Services\WorkSetup;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09T12:00:00Z'));
    $this->owner = User::factory()->create(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()]);
    config(['chart.owner_id' => $this->owner->id, 'inertia.ssr.enabled' => false]);
    $this->actingAs($this->owner);
    app(WorkSetup::class)->initialize($this->owner);
    Bus::fake();
    Http::preventStrayRequests();
    $this->domain = Domain::forUser($this->owner)->where('name', 'Writing')->firstOrFail();
    $this->project = Project::create(['user_id' => $this->owner->id, 'domain_id' => $this->domain->id, 'name' => 'Book', 'slug' => 'book', 'type' => 'ongoing', 'lifecycle' => 'active']);
    $this->tasks = collect(['Cover design', 'Edit chapter', 'Call editor'])->map(fn ($title) => Task::create(['user_id' => $this->owner->id, 'domain_id' => $this->domain->id, 'project_id' => $this->project->id, 'title' => $title]));
    $this->person = Person::create(['user_id' => $this->owner->id, 'name' => 'Alex', 'relationship' => 'other']);
    $this->travel(2)->hours();
    $this->item = function (array $data = [], ?Capture $capture = null) {
        $text = $data['excerpt'] ?? 'Set my Top 3 to Cover design and Edit chapter';
        if (! $capture) {
            $this->postJson('/captures', ['owner_id' => $this->owner->id, 'text' => $text, 'request_key' => Str::uuid()->toString(), 'captured_at' => now()->subMinute()->toISOString()])->assertAccepted();
            $capture = Capture::latest('id')->firstOrFail();
        }
        $payload = $data + ['type' => 'set_top3', 'task_refs' => ['Cover design', 'Edit chapter'], 'plan_date' => '2026-10-09', 'confidence' => .95, 'excerpt' => $text];

        return CaptureItem::create(['user_id' => $this->owner->id, 'capture_id' => $capture->id, 'sequence' => $capture->items()->count(), 'action_type' => $payload['type'], 'excerpt' => $payload['excerpt'], 'payload' => $payload, 'status' => 'pending']);
    };
    $this->focus = fn (array $data = []) => ($this->item)($data + ['type' => 'set_tomorrow_focus', 'task_refs' => null, 'body' => 'Finish the draft', 'plan_date' => '2026-10-10', 'excerpt' => 'Tomorrow focus on finishing the draft']);
    $this->clear = fn (array $data = []) => ($this->item)($data + ['type' => 'clear_waiting', 'task_refs' => null, 'plan_date' => null, 'task_ref' => 'Cover design', 'excerpt' => 'Clear the wait on Cover design']);
});

it('sets ordered today and tomorrow plans once and rolls tomorrow into today', function () {
    $item = ($this->item)(['task_refs' => ['Edit chapter', 'Cover design']]);
    $actions = app(CaptureActions::class);
    $actions->execute($item);
    $actions->execute($item);
    expect($item->fresh()->status)->toBe('executed')->and(ActionLog::count())->toBe(1);
    expect(DailyPlan::first()->top_task_ids)->toBe([$this->tasks[1]->id, $this->tasks[0]->id]);
    $tomorrow = ($this->item)(['plan_date' => '2026-10-10', 'task_refs' => ['Call editor']]);
    $actions->execute($tomorrow);
    expect(app(DailyPlanning::class)->today($this->owner)['tomorrow_tasks']->pluck('id')->all())->toBe([$this->tasks[2]->id]);
    $this->travel(1)->days();
    expect(app(DailyPlanning::class)->today($this->owner)['tasks']->pluck('id')->all())->toBe([$this->tasks[2]->id]);
});

it('changes only the selected field and restores its previous value on undo', function () {
    $plan = DailyPlan::create(['user_id' => $this->owner->id, 'plan_date' => '2026-10-09', 'top_task_ids' => [$this->tasks[2]->id], 'tomorrow_focus' => 'Original focus']);
    $this->travel(2)->minutes();
    $item = ($this->item)();
    app(CaptureActions::class)->execute($item);
    expect($plan->fresh()->tomorrow_focus)->toBe('Original focus');
    app(CaptureActions::class)->undo($item);
    app(CaptureActions::class)->undo($item);
    expect($plan->fresh()->top_task_ids)->toBe([$this->tasks[2]->id])->and($plan->fresh()->revision)->toBe(2);
    $this->travel(2)->minutes();
    $focus = ($this->focus)();
    app(CaptureActions::class)->execute($focus);
    expect($focus->fresh()->status)->toBe('executed')->and($plan->fresh()->top_task_ids)->toBe([$this->tasks[2]->id]);
    app(CaptureActions::class)->undo($focus);
    expect($plan->fresh()->tomorrow_focus)->toBe('Original focus')->and(FeedNotification::count())->toBe(2)->and(Task::count())->toBe(3);
});

it('supports an explicit empty list and preserves a revision after undoing a new plan', function () {
    $item = ($this->item)(['task_refs' => []]);
    app(CaptureActions::class)->execute($item);
    expect($item->fresh()->status)->toBe('executed')->and(DailyPlan::first()->top_task_ids)->toBe([]);
    app(CaptureActions::class)->undo($item);
    expect(DailyPlan::first()->revision)->toBe(2);
});

it('rejects invalid lists dates and confidence without creating a plan', function (array $data) {
    $item = ($this->item)($data);
    app(CaptureActions::class)->execute($item);
    expect($item->fresh()->status)->toBe('needs_triage')->and(DailyPlan::count())->toBe(0)->and(ActionLog::count())->toBe(0);
})->with([
    [['task_refs' => ['Cover design', 'Missing']]], [['task_refs' => ['Cover design', 'cover design']]],
    [['task_refs' => ['Cover design', 'Edit chapter', 'Call editor', 'Fourth']]], [['task_refs' => null]],
    [['plan_date' => '2026-10-11']], [['plan_date' => '2026-02-30']], [['confidence' => .79]],
]);

it('rejects unavailable and ambiguous tasks atomically', function (string $unavailable) {
    if ($unavailable === 'waiting') {
        $this->tasks[1]->update(['waiting_on_person_id' => $this->person->id]);
    }
    if ($unavailable === 'completed') {
        $this->tasks[1]->update(['completed_at' => now()]);
    }
    if ($unavailable === 'parked') {
        $this->domain->update(['parked' => true]);
    }
    if ($unavailable === 'someday') {
        $this->project->update(['lifecycle' => 'someday']);
    }
    if ($unavailable === 'foreign') {
        $this->tasks[1]->update(['user_id' => User::factory()->create()->id]);
    }
    if ($unavailable === 'duplicate') {
        $this->tasks[1]->replicate()->save();
    }
    $item = ($this->item)();
    app(CaptureActions::class)->execute($item);
    expect($item->fresh()->status)->toBe('needs_triage')->and(DailyPlan::count())->toBe(0);
})->with(['waiting', 'completed', 'parked', 'someday', 'foreign', 'duplicate']);

it('keeps newer plans and requires a current revision during review', function () {
    $item = ($this->item)();
    $plan = DailyPlan::create(['user_id' => $this->owner->id, 'plan_date' => '2026-10-09', 'top_task_ids' => [$this->tasks[2]->id], 'revision' => 1]);
    app(CaptureActions::class)->execute($item);
    expect($item->fresh()->status)->toBe('needs_triage');
    $data = ['type' => 'set_top3', 'plan_date' => '2026-10-09', 'task_refs' => [], 'top_task_ids' => [$this->tasks[0]->id], 'plan_revision' => 0];
    $this->put(route('capture-items.resolve', $item), $data)->assertSessionHasNoErrors();
    expect($plan->fresh()->top_task_ids)->toBe([$this->tasks[2]->id]);
    $this->put(route('capture-items.resolve', $item), array_replace($data, ['plan_revision' => 1]))->assertSessionHasNoErrors();
    expect($item->fresh()->status)->toBe('executed')->and($plan->fresh()->top_task_ids)->toBe([$this->tasks[0]->id]);
    $plan->refresh()->update(['tomorrow_focus' => 'Later edit', 'revision' => 3]);
    $this->post(route('capture-items.undo', $item))->assertSessionHasErrors('undo');
});

it('requires review after midnight or timezone changes and allows explicit retargeting', function (bool $timezone) {
    $item = ($this->item)();
    if ($timezone) {
        $this->put(route('work.timezone'), ['timezone' => 'America/New_York'])->assertSessionHasNoErrors();
    } else {
        $this->travel(1)->days();
    }
    app(CaptureActions::class)->execute($item);
    expect($item->fresh()->status)->toBe('needs_triage')->and(DailyPlan::count())->toBe(0);
    $this->put(route('capture-items.resolve', $item), ['type' => 'set_top3', 'plan_date' => $timezone ? '2026-10-09' : '2026-10-10', 'task_refs' => [], 'top_task_ids' => [$this->tasks[0]->id], 'plan_revision' => 0])->assertSessionHasNoErrors();
    expect($item->fresh()->status)->toBe('executed');
})->with([true, false]);

it('validates focus length and its target day', function (array $data) {
    $item = ($this->focus)($data);
    app(CaptureActions::class)->execute($item);
    expect($item->fresh()->status)->toBe('needs_triage')->and(DailyPlan::count())->toBe(0);
})->with([[['body' => str_repeat('x', 281)]], [['plan_date' => '2026-10-09']], [['body' => '']]]);

it('combines different plan fields from one dump but blocks a repeated field', function () {
    $item = ($this->item)(['excerpt' => 'Set my Top 3. Tomorrow focus on the draft.']);
    $focus = ($this->item)(['type' => 'set_tomorrow_focus', 'task_refs' => null, 'body' => 'Draft', 'plan_date' => '2026-10-10', 'excerpt' => 'Tomorrow focus on the draft.'], Capture::findOrFail($item->capture_id));
    app(CaptureActions::class)->execute($item);
    app(CaptureActions::class)->execute($focus);
    expect($focus->fresh()->status)->toBe('executed')->and(DailyPlan::first()->tomorrow_focus)->toBe('Draft')->and(DailyPlan::first()->top_task_ids)->toHaveCount(2);
    $again = ($this->item)(['excerpt' => 'Set my Top 3.'], Capture::findOrFail($item->capture_id));
    app(CaptureActions::class)->execute($again);
    expect($again->fresh()->status)->toBe('needs_triage');
    $this->post(route('capture-items.undo', $item))->assertSessionHasErrors('undo');
});

it('previews plans without writes and includes current plans in review options', function () {
    $item = ($this->focus)();
    $preview = app(CaptureActions::class)->preview($this->owner, $item->excerpt, $item->payload, Capture::findOrFail($item->capture_id));
    expect($preview['outcome'])->toBe('would_file')->and(DailyPlan::count())->toBe(0);
    app(CaptureActions::class)->execute($item);
    $this->get(route('captures.show', Capture::findOrFail($item->capture_id)))->assertInertia(fn (Assert $page) => $page->has('options.plans', 2)->where('options.plans.0.tomorrow_focus', 'Finish the draft'));
    expect(app(CaptureActions::class)->preview($this->owner, $item->excerpt, $item->payload, Capture::findOrFail($item->capture_id))['outcome'])->toBe('needs_triage');
    $this->travel(1)->days();
    expect(app(DailyPlanning::class)->today($this->owner)['today_focus'])->toBe('Finish the draft');
});

it('clears a task wait without completing or touching work and supports undo', function () {
    $task = $this->tasks[0];
    $task->update(['waiting_on_person_id' => $this->person->id, 'waiting_since' => now()->subDay(), 'wait_expected_by' => '2026-10-12']);
    $this->travel(2)->minutes();
    $item = ($this->clear)();
    app(CaptureActions::class)->execute($item);
    expect($item->fresh()->status)->toBe('executed')->and($task->fresh()->waiting_on_person_id)->toBeNull()->and($task->fresh()->completed_at)->toBeNull()->and($this->project->fresh()->last_touched_at)->toBeNull();
    app(CaptureActions::class)->undo($item);
    expect($task->fresh()->waiting_on_person_id)->toBe($this->person->id)->and($task->fresh()->wait_expected_by->toDateString())->toBe('2026-10-12');
});

it('clears a project handoff at recording time and restores it on undo', function () {
    $this->project->update(['holder' => 'other', 'holder_person_id' => $this->person->id, 'holder_since' => now()->subDay()]);
    $this->travel(2)->minutes();
    $item = ($this->clear)(['task_ref' => null, 'project_ref' => 'Book', 'person_ref' => 'Alex']);
    app(CaptureActions::class)->execute($item);
    expect($item->fresh()->status)->toBe('executed')->and($this->project->fresh()->holder->value)->toBe('me')->and($this->project->fresh()->holder_since->equalTo(Capture::findOrFail($item->capture_id)->client_captured_at))->toBeTrue();
    app(CaptureActions::class)->undo($item);
    expect($this->project->fresh()->holder_person_id)->toBe($this->person->id);
});

it('does not clear absent mismatched or newer waits', function (string $kind) {
    if ($kind !== 'absent') {
        $this->tasks[0]->update(['waiting_on_person_id' => $this->person->id, 'waiting_since' => now()->subDay()]);
    }
    if ($kind === 'mismatch') {
        Person::create(['user_id' => $this->owner->id, 'name' => 'Jamie', 'relationship' => 'other']);
    }
    $this->travel(2)->minutes();
    $item = ($this->clear)($kind === 'mismatch' ? ['person_ref' => 'Jamie'] : []);
    if ($kind === 'newer') {
        $this->patch(route('tasks.waiting', $this->tasks[0]), ['waiting' => true, 'revision' => 0, 'person_id' => $this->person->id, 'expected_by' => '2026-10-11'])->assertSessionHasNoErrors();
    }
    app(CaptureActions::class)->execute($item);
    expect($item->fresh()->status)->toBe('needs_triage')->and(ActionLog::count())->toBe(0);
})->with(['absent', 'mismatch', 'newer']);

it('rejects an intervening edit even between two fields in one capture', function () {
    $item = ($this->item)(['excerpt' => 'Set my Top 3. Tomorrow focus on Draft.']);
    app(CaptureActions::class)->execute($item);
    DailyPlan::first()->update(['tomorrow_focus' => 'Newer manual focus', 'revision' => 2]);
    $focus = ($this->item)(['type' => 'set_tomorrow_focus', 'task_refs' => null, 'body' => 'Draft', 'plan_date' => '2026-10-10', 'excerpt' => 'Tomorrow focus on Draft.'], Capture::findOrFail($item->capture_id));
    app(CaptureActions::class)->execute($focus);
    expect($focus->fresh()->status)->toBe('needs_triage')->and(DailyPlan::first()->tomorrow_focus)->toBe('Newer manual focus');
});

it('rejects unavailable owned choices on reviewed lists', function () {
    $foreign = $this->tasks[0]->replicate();
    $foreign->user_id = User::factory()->create()->id;
    $foreign->save();
    $item = ($this->item)(['task_refs' => ['Missing']]);
    $this->put(route('capture-items.resolve', $item), ['type' => 'set_top3', 'plan_date' => '2026-10-09', 'task_refs' => [], 'top_task_ids' => [$foreign->id], 'plan_revision' => 0])->assertSessionHasNoErrors();
    expect($item->fresh()->status)->toBe('needs_triage')->and(DailyPlan::count())->toBe(0);
});

it('requires a fresh wait revision when reviewing clear waiting', function () {
    $this->tasks[0]->update(['waiting_on_person_id' => $this->person->id, 'waiting_since' => now()->subDay(), 'wait_revision' => 1]);
    $item = ($this->clear)();
    $data = ['type' => 'clear_waiting', 'task_id' => $this->tasks[0]->id, 'work_revision' => 0];
    $this->put(route('capture-items.resolve', $item), $data)->assertSessionHasNoErrors();
    expect($item->fresh()->status)->toBe('needs_triage');
    $this->put(route('capture-items.resolve', $item), array_replace($data, ['work_revision' => 1]))->assertSessionHasNoErrors();
    expect($item->fresh()->status)->toBe('executed')->and($this->tasks[0]->fresh()->waiting_on_person_id)->toBeNull();
});

it('expires planning undo after seven days', function () {
    $item = ($this->focus)();
    app(CaptureActions::class)->execute($item);
    $this->travel(8)->days();
    $this->post(route('capture-items.undo', $item))->assertSessionHasErrors('undo');
    expect(DailyPlan::first()->tomorrow_focus)->toBe('Finish the draft');
});

it('uses the local calendar through midnight and daylight saving transitions', function (string $timestamp, string $today, string $tomorrow) {
    $this->travelTo(CarbonImmutable::parse($timestamp));
    $item = ($this->focus)(['plan_date' => $tomorrow]);
    app(CaptureActions::class)->execute($item);
    expect($item->fresh()->status)->toBe('executed')->and(DailyPlan::first()->plan_date->toDateString())->toBe($today);
})->with([
    ['2026-10-10T04:30:00Z', '2026-10-09', '2026-10-10'],
    ['2026-03-08T08:30:00Z', '2026-03-08', '2026-03-09'],
    ['2026-11-01T07:30:00Z', '2026-11-01', '2026-11-02'],
]);

it('rejects future recording times for both planning and clearing waits', function () {
    $this->tasks[0]->update(['waiting_on_person_id' => $this->person->id]);
    foreach ([($this->focus)(), ($this->clear)()] as $item) {
        Capture::findOrFail($item->capture_id)->update(['client_captured_at' => now()->addMinute()]);
        app(CaptureActions::class)->execute($item);
        expect($item->fresh()->status)->toBe('needs_triage')->and($item->fresh()->error)->toContain('future');
    }
});

it('processes a mixed clearing and planning capture exactly once with truthful confirmation', function () {
    config(['chart.capture.enabled' => true, 'chart.capture.key' => 'synthetic-test-key']);
    $this->tasks[0]->update(['waiting_on_person_id' => $this->person->id, 'waiting_since' => now()->subDay()]);
    $this->travel(2)->minutes();
    $actions = [
        ['type' => 'clear_waiting', 'task_ref' => 'Cover design', 'excerpt' => 'Clear the wait on Cover design.', 'confidence' => .95],
        ['type' => 'set_top3', 'task_refs' => ['Cover design', 'Edit chapter'], 'plan_date' => '2026-10-09', 'excerpt' => 'Today my Top 3 is Cover design and Edit chapter.', 'confidence' => .95],
        ['type' => 'set_tomorrow_focus', 'body' => 'Finish the draft', 'plan_date' => '2026-10-10', 'excerpt' => 'Tomorrow focus on finishing the draft.', 'confidence' => .95],
    ];
    $this->postJson('/captures', ['owner_id' => $this->owner->id, 'text' => implode(' ', array_column($actions, 'excerpt')), 'request_key' => Str::uuid()->toString(), 'captured_at' => now()->subMinute()->toISOString()])->assertAccepted();
    $capture = Capture::latest('id')->firstOrFail();
    Http::fake(['https://api.openai.com/v1/responses' => Http::response(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['actions' => $actions])]]]]])]);
    app(CaptureProcessor::class)->process($capture->id, $this->owner->id);
    app(CaptureProcessor::class)->process($capture->id, $this->owner->id);
    expect($capture->fresh()->status)->toBe('executed')->and(ActionLog::count())->toBe(3)->and(Task::count())->toBe(3)
        ->and(DailyPlan::first()->top_task_ids)->toBe([$this->tasks[0]->id, $this->tasks[1]->id])
        ->and(DailyPlan::first()->tomorrow_focus)->toBe('Finish the draft');
    expect(app(CaptureService::class)->confirmation($capture)['spoken_confirmation'])->toBe('Saved. 3 filed.');
});
