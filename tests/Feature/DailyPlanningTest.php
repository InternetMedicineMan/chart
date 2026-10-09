<?php

use App\Models\AppSetting;
use App\Models\DailyPlan;
use App\Models\Domain;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\WorkSetup;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09T01:00:00Z'));
    $this->owner = User::factory()->create(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()]);
    config(['chart.owner_id' => $this->owner->id, 'inertia.ssr.enabled' => false]);
    app(WorkSetup::class)->initialize($this->owner);
    $this->actingAs($this->owner);
    Http::preventStrayRequests();
    $this->inbox = Domain::forUser($this->owner)->where('is_inbox', true)->firstOrFail();
    $this->task = fn (array $attributes = []) => Task::create($attributes + ['user_id' => $this->owner->id, 'domain_id' => $this->inbox->id, 'title' => 'A thing to do']);
    $this->data = fn (array $overrides = []) => $overrides + ['plan_date' => '2026-10-08', 'revision' => 0, 'top_task_ids' => [], 'tomorrow_focus' => null];
});

it('shows an empty local day without creating records or making AI calls', function () {
    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->where('dailyPlan.plan_date', '2026-10-08')->where('dailyPlan.revision', 0)
        ->has('dailyPlan.tasks', 0)->where('dailyPlan.tomorrow_focus', null)->where('dailyPlan.today_focus', null));
    expect(DailyPlan::count())->toBe(0);
    Http::assertNothingSent();
});

it('saves up to three existing tasks in chosen order without changing their deadlines', function () {
    $tasks = collect(range(1, 3))->map(fn ($number) => ($this->task)(['title' => 'Task '.$number]));
    $ids = $tasks->reverse()->pluck('id')->values()->all();
    $this->from('/dashboard')->put('/daily-plan', ($this->data)(['top_task_ids' => $ids, 'tomorrow_focus' => 'Make space for the proposal']))->assertRedirect('/dashboard')->assertSessionHasNoErrors();
    expect(DailyPlan::count())->toBe(1)->and(DailyPlan::first()->top_task_ids)->toBe($ids)->and(Task::count())->toBe(3)
        ->and(Task::whereNotNull('due_date')->count())->toBe(0);
    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->has('dailyPlan.tasks', 3)->where('dailyPlan.tasks.0.id', $ids[0])->where('dailyPlan.revision', 1)
        ->where('dailyPlan.tomorrow_focus', 'Make space for the proposal'));
});

it('rejects four choices duplicate choices and overly long focus text', function () {
    $ids = collect(range(1, 4))->map(fn () => ($this->task)()->id)->all();
    $this->put('/daily-plan', ($this->data)(['top_task_ids' => $ids]))->assertSessionHasErrors('top_task_ids');
    $this->put('/daily-plan', ($this->data)(['top_task_ids' => [$ids[0], $ids[0]]]))->assertSessionHasErrors('top_task_ids.0');
    $this->put('/daily-plan', ($this->data)(['tomorrow_focus' => str_repeat('a', 281)]))->assertSessionHasErrors('tomorrow_focus');
    expect(DailyPlan::count())->toBe(0);
});

it('allows focus by itself and clearing both focus and choices', function () {
    $this->put('/daily-plan', ($this->data)(['tomorrow_focus' => 'Read and think']))->assertSessionHasNoErrors();
    $this->put('/daily-plan', ($this->data)(['revision' => 1]))->assertSessionHasNoErrors();
    expect(DailyPlan::first()->top_task_ids)->toBe([])->and(DailyPlan::first()->tomorrow_focus)->toBeNull()
        ->and(DailyPlan::first()->revision)->toBe(2)->and(Task::count())->toBe(0);
});

it('refuses a stale tab rather than overwriting newer choices', function () {
    $first = ($this->task)(['title' => 'First choice']);
    $second = ($this->task)(['title' => 'Newer choice']);
    $this->put('/daily-plan', ($this->data)(['top_task_ids' => [$first->id]]))->assertSessionHasNoErrors();
    $this->put('/daily-plan', ($this->data)(['revision' => 1, 'top_task_ids' => [$second->id]]))->assertSessionHasNoErrors();
    $this->put('/daily-plan', ($this->data)(['revision' => 1, 'tomorrow_focus' => 'Stale edit']))->assertSessionHasErrors('revision');
    expect(DailyPlan::first()->top_task_ids)->toBe([$second->id])->and(DailyPlan::first()->revision)->toBe(2)
        ->and(DailyPlan::count())->toBe(1);
});

it('carries only yesterday’s focus into a new day and preserves the previous plan', function () {
    $task = ($this->task)();
    $this->put('/daily-plan', ($this->data)(['top_task_ids' => [$task->id], 'tomorrow_focus' => 'The proposal']))->assertSessionHasNoErrors();
    $this->travelTo(CarbonImmutable::parse('2026-10-09T05:00:00Z'));
    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page
        ->where('dailyPlan.plan_date', '2026-10-09')->where('dailyPlan.today_focus', 'The proposal')->has('dailyPlan.tasks', 0)
        ->where('dailyPlan.tomorrow_focus', null));
    $this->put('/daily-plan', ($this->data)(['revision' => 1]))->assertSessionHasErrors('plan_date');
    expect(DailyPlan::count())->toBe(1)->and(DailyPlan::first()->top_task_ids)->toBe([$task->id]);
    $this->travel(1)->days();
    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('dailyPlan.today_focus', null));
});

it('uses the configured timezone and handles local date boundaries around DST', function (string $instant, string $date) {
    $this->travelTo(CarbonImmutable::parse($instant));
    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('dailyPlan.plan_date', $date));
    $this->put('/daily-plan', ($this->data)(['plan_date' => $date]))->assertSessionHasNoErrors();
    expect(DailyPlan::first()->plan_date->toDateString())->toBe($date);
})->with([
    ['2026-03-08T05:59:59Z', '2026-03-07'],
    ['2026-03-08T08:01:00Z', '2026-03-08'],
    ['2026-11-01T04:59:59Z', '2026-10-31'],
    ['2026-11-01T07:01:00Z', '2026-11-01'],
]);

it('rejects saving a plan made before a timezone change that moves the local day', function () {
    AppSetting::forUser($this->owner)->where('key', 'timezone')->update(['value' => json_encode('Asia/Tokyo')]);
    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('dailyPlan.plan_date', '2026-10-09'));
    $this->put('/daily-plan', ($this->data)())->assertSessionHasErrors('plan_date');
});

it('keeps completed selections visible and supports reopening without changing the plan', function () {
    $task = ($this->task)();
    $this->put('/daily-plan', ($this->data)(['top_task_ids' => [$task->id]]))->assertSessionHasNoErrors();
    $this->patch(route('tasks.completion', $task->id), ['completed' => true])->assertSessionHasNoErrors();
    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->has('dailyPlan.tasks', 1)->where('dailyPlan.tasks.0.completed_at', fn ($date) => $date !== null));
    $this->getJson('/daily-plan/tasks')->assertJsonPath('total', 0);
    $this->put('/daily-plan', ($this->data)(['revision' => 1, 'top_task_ids' => [$task->id], 'tomorrow_focus' => 'A new day']))->assertSessionHasNoErrors();
    $this->patch(route('tasks.completion', $task->id), ['completed' => false])->assertSessionHasNoErrors();
    expect(DailyPlan::first()->top_task_ids)->toBe([$task->id]);
});

it('rejects unavailable choices and does not expose other owners in the picker', function () {
    $other = User::factory()->create();
    app(WorkSetup::class)->initialize($other);
    $foreign = ($this->task)(['user_id' => $other->id, 'domain_id' => Domain::forUser($other)->where('is_inbox', true)->first()->id]);
    $deleted = ($this->task)();
    $deleted->delete();
    $completed = ($this->task)(['completed_at' => now()]);
    $parkedDomain = Domain::forUser($this->owner)->where('is_inbox', false)->first();
    $parkedDomain->update(['parked' => true]);
    $parked = ($this->task)(['domain_id' => $parkedDomain->id]);
    $project = Project::create(['user_id' => $this->owner->id, 'domain_id' => $this->inbox->id, 'name' => 'Someday', 'slug' => 'someday', 'lifecycle' => 'someday', 'type' => 'ongoing']);
    $someday = ($this->task)(['project_id' => $project->id]);
    foreach ([$foreign, $deleted, $completed, $parked, $someday] as $task) {
        $this->put('/daily-plan', ($this->data)(['top_task_ids' => [$task->id]]))->assertSessionHasErrors('top_task_ids');
    }
    $this->getJson('/daily-plan/tasks')->assertJsonPath('total', 0);
    expect(DailyPlan::count())->toBe(0);
});

it('lets the owner replace a selected task that was subsequently deleted or parked', function () {
    $task = ($this->task)();
    $this->put('/daily-plan', ($this->data)(['top_task_ids' => [$task->id]]))->assertSessionHasNoErrors();
    $task->delete();
    $this->get('/dashboard')->assertInertia(fn (Assert $page) => $page->has('dailyPlan.tasks', 0)->where('dailyPlan.unavailable_count', 1));
    $replacement = ($this->task)(['title' => 'Replacement']);
    $this->put('/daily-plan', ($this->data)(['revision' => 1, 'top_task_ids' => [$replacement->id]]))->assertSessionHasNoErrors();
    expect(DailyPlan::first()->top_task_ids)->toBe([$replacement->id]);
});

it('paginates and searches all eligible tasks without a fixed candidate ceiling', function () {
    foreach (range(1, 21) as $number) {
        ($this->task)(['title' => 'Plan task '.$number]);
    }
    $this->getJson('/daily-plan/tasks')->assertJsonCount(15, 'data')->assertJsonPath('total', 21);
    $this->getJson('/daily-plan/tasks?page=2')->assertJsonCount(6, 'data');
    $this->getJson('/daily-plan/tasks?q=task%2021')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Plan task 21');
    expect($this->getJson('/daily-plan/tasks')->headers->get('Cache-Control'))->toContain('no-store');
    $this->getJson('/daily-plan/tasks?page=0')->assertUnprocessable();
    Http::assertNothingSent();
});

it('requires the owner and confirmed two factor for planning routes', function () {
    $this->owner->update(['two_factor_confirmed_at' => null]);
    $this->putJson('/daily-plan', ($this->data)())->assertForbidden();
    $this->getJson('/daily-plan/tasks')->assertForbidden();
    $this->actingAs(User::factory()->create(['two_factor_confirmed_at' => now()]));
    $this->putJson('/daily-plan', ($this->data)())->assertForbidden();
    $this->getJson('/daily-plan/tasks')->assertForbidden();
    auth()->forgetGuards();
    $this->getJson('/daily-plan/tasks')->assertUnauthorized();
    $this->putJson('/daily-plan', ($this->data)())->assertUnauthorized();
    expect(DailyPlan::count())->toBe(0);
});
