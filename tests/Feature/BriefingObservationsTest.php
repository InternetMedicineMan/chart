<?php

use App\Models\AppSetting;
use App\Models\Capture;
use App\Models\Domain;
use App\Models\Note;
use App\Models\Observation;
use App\Models\Person;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\BriefingObservations;
use App\Services\WorkSetup;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09T12:00:00Z'));
    $this->owner = User::factory()->create(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()]);
    config(['chart.owner_id' => $this->owner->id, 'inertia.ssr.enabled' => false]);
    $this->actingAs($this->owner);
    app(WorkSetup::class)->initialize($this->owner);
    Http::preventStrayRequests();
    $this->domain = Domain::forUser($this->owner)->where('name', 'Writing')->firstOrFail();
    $this->project = Project::create(['user_id' => $this->owner->id, 'domain_id' => $this->domain->id, 'name' => 'Book', 'slug' => 'book', 'type' => 'ongoing', 'lifecycle' => 'active']);
    $this->task = fn (array $data = []) => Task::create($data + ['user_id' => $this->owner->id, 'domain_id' => $this->domain->id, 'project_id' => $this->project->id, 'title' => 'Draft', 'due_date' => '2026-10-09']);
    $this->observations = app(BriefingObservations::class);
});

it('scores due tasks and caps briefing while paginating owned observations', function () {
    ($this->task)(['due_date' => '2026-10-08']);
    ($this->task)(['due_date' => '2026-10-09']);
    ($this->task)(['due_date' => '2026-10-12']);
    ($this->task)(['due_date' => '2026-10-13']);
    foreach (range(1, 20) as $i) {
        ($this->task)(['title' => 'Task '.$i]);
    }
    $this->observations->refresh($this->owner, true);
    expect(Observation::count())->toBe(23)->and(Observation::where('score', 155)->count())->toBe(1)->and(Observation::where('score', 55)->count())->toBe(1);
    $this->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page->has('observations.items', 5)->where('observations.total', 23)->where('observations.items.0.score', 155));
    $this->get('/observations')->assertOk()->assertInertia(fn (Assert $page) => $page->has('observations.data', 20)->where('observations.total', 23));
    Http::assertNothingSent();
});

it('deduplicates by day and week and carries snooze across buckets', function () {
    ($this->task)();
    $this->domain->update(['last_touched_at' => now()->subDays(20)]);
    $this->observations->refresh($this->owner, true);
    $quiet = Observation::where('rule_type', 'domain_quiet')->firstOrFail();
    $due = Observation::where('rule_type', 'task_due')->firstOrFail();
    $this->patch(route('observations.update', $quiet), ['action' => 'dismiss'])->assertRedirect();
    $this->patch(route('observations.update', $due), ['action' => 'snooze', 'days' => 7])->assertRedirect();
    $this->observations->refresh($this->owner, true);
    expect(Observation::count())->toBe(2)->and(Observation::visible()->count())->toBe(0);
    $this->travel(1)->days();
    $this->observations->refresh($this->owner, true);
    expect(Observation::count())->toBe(3)->and(Observation::visible()->count())->toBe(0)->and($due->fresh()->resolved_at)->not->toBeNull();
    $this->travel(3)->days();
    $this->observations->refresh($this->owner, true);
    expect(Observation::where('rule_type', 'domain_quiet')->visible()->count())->toBe(1)->and(Observation::where('rule_type', 'task_due')->visible()->count())->toBe(0);
});

it('resolves on read after completion or parking without creating new observations', function () {
    $task = ($this->task)();
    $this->observations->refresh($this->owner, true);
    $task->update(['completed_at' => now()]);
    expect($this->observations->briefing($this->owner)['total'])->toBe(0)->and(Observation::first()->resolved_at)->not->toBeNull();
    ($this->task)(['title' => 'Another']);
    expect($this->observations->briefing($this->owner)['total'])->toBe(0);
    $this->observations->refresh($this->owner, true);
    $this->domain->update(['parked' => true]);
    expect($this->observations->briefing($this->owner)['total'])->toBe(0);
});

it('honors quiet switches and ignores parked or unavailable work', function () {
    $this->domain->update(['last_touched_at' => now()->subDays(40), 'quiet_enabled' => false]);
    $this->project->update(['last_touched_at' => now()->subDays(40), 'quiet_enabled' => false]);
    $this->observations->refresh($this->owner, true);
    expect(Observation::visible()->count())->toBe(0);
    $this->project->update(['quiet_enabled' => true]);
    $this->observations->refresh($this->owner, true);
    expect(Observation::visible()->count())->toBe(1);
    $this->project->delete();
    expect($this->observations->briefing($this->owner)['total'])->toBe(0);
});

it('rolls up ideas and three old captures with local calendar boundaries', function () {
    foreach (range(1, 3) as $i) {
        Note::create(['user_id' => $this->owner->id, 'kind' => 'thought', 'body' => 'Idea', 'created_at' => '2026-09-10 01:00:00']);
        Capture::create(['user_id' => $this->owner->id, 'raw_text' => 'Review', 'request_key' => Str::uuid()->toString(), 'client_captured_at' => now()->subDays(8), 'timezone' => 'America/Chicago', 'source' => 'in_app', 'status' => 'needs_triage', 'created_at' => now()->subDays(8)]);
    }
    $this->observations->refresh($this->owner, true);
    expect(Observation::visible()->pluck('roll_up_count')->all())->toBe([3, 3])->and(Observation::visible()->where('urgency', 'low')->count())->toBe(2);
    Note::first()->update(['reviewed_at' => now()]);
    Capture::first()->update(['status' => 'executed']);
    expect($this->observations->briefing($this->owner)['total'])->toBe(0);
});

it('scores waits even before seven days when the expected response is overdue', function () {
    $person = Person::create(['user_id' => $this->owner->id, 'name' => 'Alex', 'relationship' => 'other']);
    ($this->task)(['waiting_on_person_id' => $person->id, 'waiting_since' => now()->subDays(2), 'wait_expected_by' => '2026-10-08']);
    $this->project->update(['holder' => 'other', 'holder_person_id' => $person->id, 'holder_since' => now()->subDays(10)]);
    $this->observations->refresh($this->owner, true);
    expect(Observation::where('rule_type', 'wait_aging')->count())->toBe(2)->and(Observation::where('subject_type', 'task')->first()->score)->toBe(61)->and(Observation::where('subject_type', 'project')->first()->score)->toBe(45);
});

it('enforces private ownership for observations and validates controls', function () {
    $foreign = Observation::factory()->create();
    $this->patch(route('observations.update', $foreign), ['action' => 'dismiss'])->assertNotFound();
    $this->get('/observations')->assertInertia(fn (Assert $page) => $page->where('observations.total', 0));
    ($this->task)();
    $this->observations->refresh($this->owner, true);
    $row = Observation::forUser($this->owner)->first();
    $this->patch(route('observations.update', $row), ['action' => 'snooze', 'days' => 100])->assertSessionHasErrors('days');
    auth()->logout();
    $this->get('/observations')->assertRedirect('/login');
    $this->patch(route('observations.update', $row), ['action' => 'dismiss'])->assertRedirect('/login');
});

it('expires observations after sixty days and allows restoring a current dismissed item', function () {
    ($this->task)();
    $this->observations->refresh($this->owner, true);
    $row = Observation::first();
    $this->patch(route('observations.update', $row), ['action' => 'dismiss']);
    $this->patch(route('observations.update', $row), ['action' => 'restore']);
    expect(Observation::visible()->count())->toBe(1);
    $this->travel(60)->days();
    expect($this->observations->briefing($this->owner)['total'])->toBe(0)->and($row->fresh()->resolved_at)->not->toBeNull();
});

it('runs after the local two am boundary once even across daylight saving transitions', function (string $before, string $after) {
    $this->travelTo(CarbonImmutable::parse($before));
    ($this->task)(['due_date' => now()->setTimezone('America/Chicago')->toDateString()]);
    $this->artisan('observations:refresh --scheduled')->assertSuccessful();
    expect(Observation::count())->toBe(0);
    $this->travelTo(CarbonImmutable::parse($after));
    $this->artisan('observations:refresh --scheduled')->assertSuccessful();
    expect(Observation::count())->toBeGreaterThan(0);
    $count = Observation::count();
    $this->artisan('observations:refresh --scheduled')->assertSuccessful();
    expect(Observation::count())->toBe($count)->and(AppSetting::forUser($this->owner)->where('key', 'observations_last_run')->first()->value)->toBe(now()->setTimezone('America/Chicago')->toDateString());
})->with([
    ['2027-03-14T07:59:00Z', '2027-03-14T08:00:00Z'],
    ['2026-11-01T07:59:00Z', '2026-11-01T08:00:00Z'],
]);

it('keeps query count bounded as the number of observed subjects grows', function () {
    ($this->task)();
    $this->observations->refresh($this->owner, true);
    DB::enableQueryLog();
    $this->observations->refresh($this->owner, true);
    $small = count(DB::getQueryLog());
    DB::disableQueryLog();
    foreach (range(1, 30) as $i) {
        ($this->task)(['title' => 'Extra '.$i]);
    }
    $this->observations->refresh($this->owner, true);
    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->observations->refresh($this->owner, true);
    $large = count(DB::getQueryLog());
    DB::disableQueryLog();
    expect($large)->toBe($small);
});

it('preserves dismissal when refreshing mixed existing and new observations', function () {
    $task = ($this->task)();
    $this->observations->refresh($this->owner, true);
    $row = Observation::first();
    $row->update(['dismissed_at' => now()]);
    ($this->task)(['title' => 'New']);
    $task->update(['title' => 'Changed']);
    $this->observations->refresh($this->owner, true);
    expect(Observation::count())->toBe(2)->and($row->fresh()->title)->toBe('Changed')->and($row->fresh()->dismissed_at)->not->toBeNull()->and(Observation::visible()->count())->toBe(1);
});

it('rolls the additive observation migration back and reapplies it', function () {
    $migration = require database_path('migrations/2026_10_09_205212_create_observations_table.php');
    $migration->down();
    expect(Schema::hasTable('observations'))->toBeFalse();
    $migration->up();
    ($this->task)();
    $this->observations->refresh($this->owner, true);
    expect(Observation::count())->toBe(1);
});
