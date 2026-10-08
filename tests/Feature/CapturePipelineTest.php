<?php

namespace Tests\Feature;

use App\Jobs\ParseCapture;
use App\Models\ActionLog;
use App\Models\Capture;
use App\Models\CaptureAttempt;
use App\Models\CaptureItem;
use App\Models\Domain;
use App\Models\Note;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\CaptureActions;
use App\Services\CaptureProcessor;
use App\Services\CaptureService;
use App\Services\WorkSetup;
use Illuminate\Contracts\Bus\QueueingDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CapturePipelineTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()]);
        config(['chart.owner_id' => $this->owner->id, 'chart.capture.enabled' => true, 'chart.capture.key' => 'test-only-key', 'chart.capture.connection' => 'database', 'inertia.ssr.enabled' => false]);
        app(WorkSetup::class)->initialize($this->owner);
        $this->actingAs($this->owner);
        Bus::fake();
        Http::preventStrayRequests();
    }

    private function data(string $text = 'Call the plumber'): array
    {
        return ['text' => $text, 'request_key' => Str::uuid()->toString(), 'captured_at' => '2026-10-08T09:00:00-05:00', 'owner_id' => $this->owner->id];
    }

    private function receive(string $text = 'Call the plumber'): Capture
    {
        $this->postJson('/captures', $this->data($text))->assertStatus(202);

        return Capture::latest('id')->firstOrFail();
    }

    private function action(string $type = 'create_task', array $overrides = []): array
    {
        return $overrides + ['type' => $type, 'confidence' => .95, 'excerpt' => 'Call the plumber', 'title' => 'Call the plumber', 'body' => null, 'domain_ref' => null, 'project_ref' => null, 'due_date' => null, 'due_time' => null, 'priority' => null, 'lifecycle' => null, 'target_date' => null, 'reason' => null];
    }

    private function respond(array $actions): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['https://api.openai.com/v1/responses' => Http::response([
            'status' => 'completed', 'usage' => ['input_tokens' => 400, 'output_tokens' => 80],
            'output' => [['type' => 'reasoning'], ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['actions' => $actions])]]]],
        ])]);
    }

    private function process(Capture $capture): Capture
    {
        app(CaptureProcessor::class)->process($capture->id, $capture->user_id);

        return $capture->fresh();
    }

    public function test_capture_is_saved_before_dispatch_with_exact_original_text_and_no_api_call(): void
    {
        $capture = $this->receive("  Call the plumber\nKeep the whitespace  ");
        $this->assertSame("  Call the plumber\nKeep the whitespace  ", $capture->raw_text);
        $this->assertSame('received', $capture->status);
        Bus::assertDispatched(ParseCapture::class, fn ($job) => Capture::find($job->captureId)?->raw_text === $capture->raw_text && $job->queue === 'captures' && $job->connection === 'database');
        Http::assertNothingSent();
    }

    public function test_duplicate_request_is_idempotent_but_changed_text_or_time_is_rejected(): void
    {
        $data = $this->data();
        $id = $this->postJson('/captures', $data)->assertStatus(202)->json('capture_id');
        $this->postJson('/captures', $data)->assertStatus(202)->assertJsonPath('capture_id', $id);
        $this->postJson('/captures', array_replace($data, ['text' => 'Different words']))->assertUnprocessable();
        $this->postJson('/captures', array_replace($data, ['captured_at' => '2026-10-09T09:00:00-05:00']))->assertUnprocessable();
        $this->assertSame(1, Capture::count());
        Bus::assertDispatchedTimes(ParseCapture::class, 1);
    }

    public function test_capture_requires_owner_two_factor_and_valid_outbox_owner_and_timestamp(): void
    {
        $this->postJson('/captures', array_replace($this->data(), ['owner_id' => 999]))->assertUnprocessable();
        $this->postJson('/captures', array_replace($this->data(), ['captured_at' => '2026-10-08']))->assertUnprocessable();
        $this->postJson('/captures', $this->data('   '))->assertUnprocessable();
        $this->owner->update(['two_factor_confirmed_at' => null]);
        $this->postJson('/captures', $this->data())->assertForbidden();
        $this->actingAs(User::factory()->create())->postJson('/captures', $this->data())->assertForbidden();
        $this->assertSame(0, Capture::count());
    }

    public function test_disabled_ai_retains_raw_capture_and_one_linked_inbox_fallback(): void
    {
        config(['chart.capture.enabled' => false]);
        $data = $this->data();
        $this->postJson('/captures', $data)->assertStatus(202)->assertJsonPath('status', 'failed');
        $this->postJson('/captures', $data)->assertStatus(202);
        $capture = Capture::firstOrFail();
        $this->assertSame('Call the plumber', Task::find($capture->fallback_task_id)->title);
        $this->assertStringContainsString(route('captures.show', $capture->id), Task::first()->notes);
        $this->assertSame(1, Task::count());
        Http::assertNothingSent();
        Bus::assertNothingDispatched();
    }

    public function test_five_item_dump_files_successful_siblings_and_keeps_ambiguity(): void
    {
        $capture = $this->receive('Call the plumber. Buy milk. A thought about writing. Someday build a shed. Meet someone.');
        $this->respond([
            $this->action(overrides: ['excerpt' => 'Call the plumber.']),
            $this->action(overrides: ['excerpt' => 'Buy milk.', 'title' => 'Buy milk', 'confidence' => .7]),
            $this->action('capture_idea', ['excerpt' => 'A thought about writing.', 'title' => null, 'body' => 'A thought about writing.']),
            $this->action('create_project', ['excerpt' => 'Someday build a shed.', 'title' => 'Build a shed', 'lifecycle' => 'someday']),
            $this->action('needs_triage', ['excerpt' => 'Meet someone.', 'title' => null, 'reason' => 'Choose who to meet.']),
        ]);
        $this->assertSame('partially_executed', $this->process($capture)->status);
        $this->assertSame(5, CaptureItem::count());
        $this->assertSame(2, Task::count());
        $this->assertTrue(Task::where('title', 'Buy milk')->first()->needs_review);
        $this->assertSame(1, Note::count());
        $this->assertSame('someday', Project::first()->lifecycle->value);
        $this->assertSame(4, ActionLog::count());
        $this->process($capture);
        $this->assertSame(4, ActionLog::count());
        $this->assertSame(400, CaptureAttempt::sum('input_tokens'));
        $this->assertSame('Saved. 4 filed; 1 need review.', app(CaptureService::class)->confirmation($capture)['spoken_confirmation']);
    }

    public function test_parse_failure_retries_three_times_and_never_duplicates_fallback(): void
    {
        $capture = $this->receive();
        Http::fake(['*' => Http::response(['error' => 'upstream secret body'], 500)]);
        for ($n = 0; $n < 4; $n++) {
            $this->process($capture);
            $this->travel(4)->minutes();
        }
        $this->assertSame(3, $capture->fresh()->attempts);
        $this->assertSame(3, CaptureAttempt::count());
        $this->assertSame(1, Task::count());
        $this->assertStringNotContainsString('upstream', $capture->fresh()->error);
    }

    public function test_successful_retry_replaces_untouched_fallback_without_duplicate_tasks(): void
    {
        $capture = $this->receive();
        Http::fake(['*' => Http::response([], 503)]);
        $this->process($capture);
        $fallback = $capture->fresh()->fallback_task_id;
        $this->respond([$this->action()]);
        $this->post(route('captures.retry', $capture->id))->assertSessionHasNoErrors();
        $this->assertSame('executed', $this->process($capture)->status);
        $this->assertSame(1, Task::count());
        $this->assertNotNull(Task::withTrashed()->find($fallback)->deleted_at);
        $this->process($capture);
        $this->assertSame(1, Task::count());
    }

    public function test_retry_preserves_fallback_that_the_owner_changed(): void
    {
        config(['chart.capture.enabled' => false]);
        $capture = $this->receive();
        Task::find($capture->fallback_task_id)->update(['title' => 'Already handled this manually']);
        config(['chart.capture.enabled' => true]);
        $this->respond([$this->action()]);
        $this->post(route('captures.retry', $capture->id))->assertSessionHasNoErrors();
        $this->assertSame('needs_triage', $this->process($capture)->status);
        $this->assertSame(0, CaptureItem::count());
        $this->assertSame('Already handled this manually', Task::first()->title);
    }

    public function test_reference_resolution_goes_to_triage_for_ambiguous_and_foreign_projects(): void
    {
        $domain = Domain::forUser($this->owner)->first();
        foreach (['Acme web', 'Acme app'] as $name) {
            Project::create(['user_id' => $this->owner->id, 'domain_id' => $domain->id, 'name' => $name, 'slug' => Str::uuid()]);
        }
        $other = User::factory()->create();
        $otherDomain = app(WorkSetup::class)->inbox($other);
        Project::create(['user_id' => $other->id, 'domain_id' => $otherDomain->id, 'name' => 'Private', 'slug' => 'private']);
        $capture = $this->receive();
        $this->respond([$this->action(overrides: ['project_ref' => 'Acme']), $this->action(overrides: ['project_ref' => 'Private'])]);
        $this->assertSame('needs_triage', $this->process($capture)->status);
        $this->assertSame(0, Task::count());
        $this->assertCount(2, CaptureItem::first()->candidates['projects']);
        $this->assertSame([], CaptureItem::latest('id')->first()->candidates['projects']);
    }

    public function test_low_confidence_unknown_action_bad_date_and_untraceable_excerpt_are_not_executed(): void
    {
        $capture = $this->receive();
        $this->respond([
            $this->action(overrides: ['confidence' => .59]),
            $this->action('delete_everything'),
            $this->action(overrides: ['due_date' => '2026-02-30']),
            $this->action(overrides: ['excerpt' => 'Not in original text']),
        ]);
        $this->assertSame('needs_triage', $this->process($capture)->status);
        $this->assertSame(4, CaptureItem::where('status', 'needs_triage')->count());
        $this->assertSame(0, Task::count());
        $this->post(route('capture-items.retry', CaptureItem::latest('id')->first()->id));
        $this->assertSame(0, Task::count());
    }

    public function test_manual_resolution_is_idempotent_and_cannot_set_owner_or_completion(): void
    {
        $capture = $this->receive();
        $this->respond([$this->action(overrides: ['confidence' => .4])]);
        $this->process($capture);
        $item = CaptureItem::firstOrFail();
        $data = ['type' => 'create_task', 'title' => 'Call plumber', 'user_id' => 999, 'completed_at' => now()];
        $this->put(route('capture-items.resolve', $item->id), $data)->assertSessionHasNoErrors();
        $this->put(route('capture-items.resolve', $item->id), $data)->assertSessionHasNoErrors();
        $this->assertSame(1, Task::count());
        $this->assertSame($this->owner->id, Task::first()->user_id);
        $this->assertNull(Task::first()->completed_at);
        $this->assertSame('executed', $capture->fresh()->status);
    }

    public function test_undo_is_idempotent_and_retry_never_recreates_undone_item(): void
    {
        $capture = $this->receive();
        $this->respond([$this->action()]);
        $this->process($capture);
        $item = CaptureItem::firstOrFail();
        $this->post(route('capture-items.undo', $item->id))->assertSessionHasNoErrors();
        $this->post(route('capture-items.undo', $item->id))->assertSessionHasNoErrors();
        $this->post(route('capture-items.retry', $item->id))->assertSessionHasNoErrors();
        $this->assertSame(0, Task::count());
        $this->assertSame('undone', $item->fresh()->status);
        $this->assertSame('undone', ActionLog::first()->status);
    }

    public function test_undo_preserves_later_edits_and_expires_after_seven_days(): void
    {
        $capture = $this->receive();
        $this->respond([$this->action(), $this->action(overrides: ['title' => 'Second task'])]);
        $this->process($capture);
        Task::first()->update(['title' => 'Manually edited']);
        $this->post(route('capture-items.undo', CaptureItem::first()->id))->assertSessionHasErrors('undo');
        $this->travel(8)->days();
        $this->post(route('capture-items.undo', CaptureItem::latest('id')->first()->id))->assertSessionHasErrors('undo');
        $this->assertSame(2, Task::count());
    }

    public function test_queue_dispatch_failure_saves_fallback_and_can_be_recovered(): void
    {
        Bus::swap(\Mockery::mock(QueueingDispatcher::class)->shouldReceive('dispatch')->andThrow(new \RuntimeException('queue offline'))->getMock());
        $capture = $this->receive();
        $this->assertSame('failed', $capture->status);
        $this->assertSame(1, Task::count());
        Bus::fake();
        $this->artisan('capture:recover')->assertSuccessful();
        Bus::assertDispatched(ParseCapture::class);
        $this->respond([$this->action()]);
        $this->assertSame('executed', $this->process($capture)->status);
    }

    public function test_live_lease_blocks_duplicate_jobs_and_expired_lease_recovers(): void
    {
        $capture = $this->receive();
        $capture->update(['status' => 'processing', 'attempts' => 1, 'lease' => Str::uuid(), 'lease_until' => now()->addMinutes(2)]);
        $this->respond([$this->action()]);
        $this->process($capture);
        Http::assertNothingSent();
        $this->travel(3)->minutes();
        $this->assertSame('executed', $this->process($capture)->status);
        $this->assertSame(1, Task::count());
    }

    public function test_exhausted_worker_crash_falls_back_to_inbox(): void
    {
        $capture = $this->receive();
        $capture->update(['status' => 'processing', 'attempts' => 3, 'lease' => Str::uuid(), 'lease_until' => now()->subMinute()]);
        $this->assertSame('failed', $this->process($capture)->status);
        $this->assertSame(1, Task::count());
        Http::assertNothingSent();
    }

    public function test_request_uses_current_owned_context_capture_local_date_strict_schema_and_no_store(): void
    {
        $capture = $this->receive();
        Domain::forUser($this->owner)->where('slug', 'writing')->update(['name' => 'Essays']);
        $this->respond([$this->action()]);
        $this->process($capture);
        Http::assertSent(function ($request) {
            $context = json_decode($request['input'][1]['content'], true)['context'];
            $this->assertSame('2026-10-08T09:00:00-05:00', $context['client_captured_at']);
            $this->assertContains('Essays', array_column($context['domains'], 'name'));
            $this->assertFalse($request['store']);
            $this->assertTrue($request['text']['format']['strict']);
            foreach (array_keys(CaptureActions::DEFINITIONS) as $action) {
                $this->assertStringContainsString($action.':', $request['input'][0]['content']);
                $this->assertContains($action, $request['text']['format']['schema']['properties']['actions']['items']['properties']['type']['enum']);
            }

            return true;
        });
    }

    public function test_capture_history_is_owned_searchable_and_does_not_call_ai(): void
    {
        $capture = $this->receive('Rare phrase');
        $this->receive('Other capture');
        $this->get('/intake?q=Rare')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Work/Intake')->has('captures.data', 1));
        $other = User::factory()->create();
        $capture->update(['user_id' => $other->id]);
        $this->get(route('captures.show', $capture->id))->assertNotFound();
        $this->post(route('captures.retry', $capture->id))->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_incomplete_response_is_not_executed_but_usage_is_retained(): void
    {
        $capture = $this->receive();
        Http::fake(['*' => Http::response(['status' => 'incomplete', 'usage' => ['input_tokens' => 200, 'output_tokens' => 30], 'output' => []])]);
        $this->assertSame('failed', $this->process($capture)->status);
        $this->assertSame(0, CaptureItem::count());
        $this->assertSame(200, CaptureAttempt::first()->input_tokens);
    }

    public function test_real_database_queue_stores_job_and_worker_files_capture(): void
    {
        Bus::fake()->except([ParseCapture::class]);
        $capture = $this->receive();
        $this->assertDatabaseHas('jobs', ['queue' => 'captures']);
        $this->respond([$this->action()]);
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'captures', '--once' => true, '--stop-when-empty' => true])->assertSuccessful();
        $this->assertSame('executed', $capture->fresh()->status);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_resume_after_parsing_does_not_reparse_or_repeat_successful_items(): void
    {
        $capture = $this->receive();
        $this->respond([$this->action(), $this->action(overrides: ['confidence' => .4, 'title' => 'Second task'])]);
        $this->process($capture);
        $item = CaptureItem::latest('id')->first();
        $item->update(['status' => 'pending', 'payload' => $this->action()]);
        $capture->refresh()->update(['status' => 'parsed', 'lease' => null, 'lease_until' => null]);
        $this->process($capture);
        $this->assertSame(2, Task::count());
        $this->assertSame(1, CaptureAttempt::count());
        $this->assertSame(2, ActionLog::count());
    }

    public function test_project_undo_protects_tasks_added_after_capture(): void
    {
        $capture = $this->receive('Start a project');
        $this->respond([$this->action('create_project', ['excerpt' => 'Start a project', 'title' => 'Project', 'lifecycle' => 'active'])]);
        $this->process($capture);
        $project = Project::firstOrFail();
        Task::create(['user_id' => $this->owner->id, 'domain_id' => $project->domain_id, 'project_id' => $project->id, 'title' => 'New task']);
        $this->post(route('capture-items.undo', CaptureItem::first()->id))->assertSessionHasErrors('undo');
        $this->assertSame(1, Project::count());
    }

    public function test_forged_item_routes_cannot_access_other_owners_data(): void
    {
        $capture = $this->receive();
        $this->respond([$this->action()]);
        $this->process($capture);
        $item = CaptureItem::first();
        $item->update(['user_id' => User::factory()->create()->id]);
        $this->post(route('capture-items.undo', $item->id))->assertNotFound();
        $this->post(route('capture-items.retry', $item->id))->assertNotFound();
        $this->put(route('capture-items.resolve', $item->id), ['type' => 'capture_idea', 'body' => 'Attempt'])->assertNotFound();
        $this->assertSame(1, Task::count());
    }

    public function test_omitted_words_remain_visible_and_repeated_proposals_are_merged(): void
    {
        $capture = $this->receive('Call the plumber. Buy milk.');
        $this->respond([$this->action(), $this->action()]);
        $this->assertSame('partially_executed', $this->process($capture)->status);
        $this->assertSame(1, Task::count());
        $this->assertSame(2, CaptureItem::count());
        $this->assertStringContainsString('Buy milk', CaptureItem::where('status', 'needs_triage')->first()->excerpt);
    }

    public function test_manual_choice_can_disambiguate_identical_project_names(): void
    {
        $domain = Domain::forUser($this->owner)->first();
        $project = null;
        foreach (range(1, 2) as $n) {
            $project = Project::create(['user_id' => $this->owner->id, 'domain_id' => $domain->id, 'name' => 'Acme', 'slug' => Str::uuid()]);
        }
        $capture = $this->receive();
        $this->respond([$this->action(overrides: ['project_ref' => 'Acme'])]);
        $this->process($capture);
        $this->assertSame('needs_triage', $capture->fresh()->status);
        $this->put(route('capture-items.resolve', CaptureItem::first()->id), ['type' => 'create_task', 'title' => 'Call plumber', 'project_id' => $project->id])->assertSessionHasNoErrors();
        $this->assertSame($project->id, Task::first()->project_id);
    }
}
