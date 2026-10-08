<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Project;
use App\Models\User;
use App\Services\ParserEvaluation;
use App\Services\WorkSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ParserEvaluationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private string $reportStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['two_factor_secret' => encrypt('test-secret'), 'two_factor_confirmed_at' => now()]);
        config(['chart.owner_id' => $this->owner->id, 'chart.capture.enabled' => false, 'chart.capture.key' => 'test-key-never-print', 'inertia.ssr.enabled' => false]);
        app(WorkSetup::class)->initialize($this->owner);
        $this->actingAs($this->owner);
        $this->reportStorage = sys_get_temp_dir().'/chart-parser-eval-'.Str::uuid();
        app()->useStoragePath($this->reportStorage);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->reportStorage);
        parent::tearDown();
    }

    private function data(string $text = 'Call the plumber'): array
    {
        return ['text' => $text, 'captured_at' => '2026-10-09T01:30:00Z'];
    }

    private function response(array $actions): void
    {
        Http::fake(['*' => Http::response(['status' => 'completed', 'model' => 'test-snapshot', 'usage' => ['input_tokens' => 100, 'output_tokens' => 40], 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode(['actions' => $actions])]]]]])]);
    }

    private function action(array $data = []): array
    {
        return $data + ['type' => 'create_task', 'title' => 'Call the plumber', 'confidence' => .95, 'excerpt' => 'Call the plumber'];
    }

    private function recordCounts(): array
    {
        return collect(['captures', 'capture_attempts', 'capture_items', 'action_logs', 'tasks', 'projects', 'notes', 'domains', 'jobs'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
    }

    public function test_preview_returns_server_checked_proposals_without_writing_any_records(): void
    {
        $before = $this->recordCounts();
        $this->response([$this->action()]);
        $this->postJson(route('parser.preview'), $this->data())->assertOk()
            ->assertJsonPath('items.0.outcome', 'would_file')->assertJsonPath('items.0.domain', 'Inbox')
            ->assertJsonPath('model', 'test-snapshot')->assertJsonPath('usage.input_tokens', 100);
        $this->assertSame($before, $this->recordCounts());
        $this->assertFalse(config('chart.capture.enabled'));
        Http::assertSent(fn ($request) => json_decode($request['input'][1]['content'], true)['context']['client_captured_at'] === '2026-10-08T20:30:00-05:00' && $request['store'] === false);
    }

    public function test_ambiguous_references_low_confidence_and_uncovered_words_are_previewed_as_review(): void
    {
        $domain = Domain::forUser($this->owner)->first();
        foreach (['Acme web', 'Acme app'] as $name) {
            Project::create(['user_id' => $this->owner->id, 'domain_id' => $domain->id, 'name' => $name, 'slug' => Str::uuid()]);
        }
        $before = $this->recordCounts();
        $this->response([$this->action(['project_ref' => 'Acme']), $this->action(['confidence' => .4])]);
        $this->postJson(route('parser.preview'), $this->data('Call the plumber. Buy milk.'))->assertOk()
            ->assertJsonPath('items.0.outcome', 'needs_triage')->assertJsonPath('items.1.outcome', 'needs_triage')->assertJsonPath('items.2.outcome', 'needs_triage');
        $this->assertSame($before, $this->recordCounts());
    }

    public function test_preview_only_sends_the_current_owners_context(): void
    {
        $other = User::factory()->create();
        Domain::create(['user_id' => $other->id, 'name' => 'Other private domain', 'slug' => 'private']);
        $this->response([$this->action()]);
        $this->postJson(route('parser.preview'), $this->data())->assertOk();
        Http::assertSent(fn ($request) => ! str_contains($request['input'][1]['content'], 'Other private domain'));
    }

    public function test_missing_key_never_calls_api_and_status_never_exposes_key(): void
    {
        $this->get(route('work.settings'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('parserStatus.keyConfigured', true)->where('parserStatus.enabled', false)->missing('parserStatus.key'));
        config(['chart.capture.key' => null]);
        $this->postJson(route('parser.preview'), $this->data())->assertUnprocessable()->assertJsonPath('reason', 'missing_key');
        $this->artisan('chart:ai-check')->expectsOutputToContain('Missing')->assertFailed();
        Http::assertNothingSent();
    }

    public function test_preview_requires_owner_and_two_factor(): void
    {
        $this->owner->update(['two_factor_confirmed_at' => null]);
        $this->postJson(route('parser.preview'), $this->data())->assertForbidden();
        $this->actingAs(User::factory()->create())->postJson(route('parser.preview'), $this->data())->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_preview_is_rate_limited(): void
    {
        config(['chart.capture.key' => null]);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('parser.preview'), $this->data())->assertUnprocessable();
        }
        $this->postJson(route('parser.preview'), $this->data())->assertStatus(429);
        Http::assertNothingSent();
    }

    public function test_blank_preview_and_missing_timestamp_never_call_api(): void
    {
        $this->postJson(route('parser.preview'), $this->data('   '))->assertUnprocessable()->assertJsonValidationErrors('text');
        $this->postJson(route('parser.preview'), ['text' => 'Call the plumber'])->assertUnprocessable()->assertJsonValidationErrors('captured_at');
        Http::assertNothingSent();
    }

    public function test_provider_failures_have_actionable_messages_without_raw_error_or_key(): void
    {
        foreach ([[401, 'invalid_api_key', 'authentication'], [403, 'forbidden', 'permission'], [404, 'model_not_found', 'model'], [429, 'insufficient_quota', 'billing'], [429, 'rate_limit_exceeded', 'rate_limit']] as [$status, $code, $reason]) {
            Http::swap(new Factory);
            Http::fake(['*' => Http::response(['error' => ['code' => $code, 'message' => 'private-provider-body test-key-never-print']], $status)]);
            $response = $this->postJson(route('parser.preview'), $this->data())->assertUnprocessable()->assertJsonPath('reason', $reason);
            $this->assertStringNotContainsString('private-provider-body', $response->getContent());
            $this->assertStringNotContainsString('test-key-never-print', $response->getContent());
        }
    }

    public function test_evaluation_listing_and_unknown_case_make_no_requests(): void
    {
        $this->artisan('parser:eval')->expectsOutputToContain('Listing only')->assertSuccessful();
        $this->artisan('parser:eval', ['--case' => ['not_real'], '--live' => true])->expectsOutputToContain('Unknown fixture')->assertFailed();
        Http::assertNothingSent();
        $this->assertDirectoryDoesNotExist($this->reportStorage.'/app/private/parser-evals');
    }

    public function test_malformed_provider_content_is_a_safe_preview_error(): void
    {
        Http::fake(['*' => Http::response(['status' => 'completed', 'usage' => ['input_tokens' => 100], 'output' => [['content' => [['type' => 'output_text', 'text' => ['unexpected']]]]]])]);
        $before = $this->recordCounts();
        $this->postJson(route('parser.preview'), $this->data())->assertUnprocessable()
            ->assertJsonPath('reason', 'invalid_output')->assertJsonPath('usage.input_tokens', 100);
        $this->assertSame($before, $this->recordCounts());
    }

    public function test_billing_credit_error_is_distinguished_from_temporary_throttling(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'Check billing: no credits available. private-provider-body']], 429)]);
        $this->postJson(route('parser.preview'), $this->data())->assertUnprocessable()->assertJsonPath('reason', 'billing')
            ->assertDontSee('private-provider-body');
    }

    public function test_live_evaluation_uses_synthetic_context_and_writes_only_a_private_report(): void
    {
        $before = $this->recordCounts();
        $this->response([$this->action(['title' => 'Renew SSL cert', 'excerpt' => 'Add a task to renew the SSL cert'])]);
        $this->artisan('parser:eval', ['--live' => true, '--case' => ['inbox_task'], '--model' => 'test-model'])
            ->expectsOutputToContain('PASS inbox_task')->expectsOutputToContain('1/1 selected cases passed')->assertSuccessful();
        $this->assertSame($before, $this->recordCounts());
        Http::assertSent(fn ($request) => $request['model'] === 'test-model' && str_contains($request['input'][1]['content'], 'Acme website'));
        $files = File::files($this->reportStorage.'/app/private/parser-evals');
        $this->assertCount(1, $files);
        $report = json_decode(File::get($files[0]->getPathname()), true);
        $this->assertTrue($report['cases'][0]['passed']);
        $this->assertSame(100, $report['cases'][0]['usage']['input_tokens']);
        $this->assertSame('gpt-6.1-sol', config('chart.capture.model'));
    }

    public function test_live_eval_stops_on_billing_failure_and_reports_unattempted_cases(): void
    {
        $before = $this->recordCounts();
        Http::fake(['*' => Http::response(['error' => ['code' => 'insufficient_quota', 'message' => 'private']], 429)]);
        $this->artisan('parser:eval', ['--live' => true, '--case' => ['inbox_task', 'idea']])->expectsOutputToContain('0/2 selected cases passed')->assertFailed();
        Http::assertSentCount(1);
        $report = json_decode(File::get(File::files($this->reportStorage.'/app/private/parser-evals')[0]->getPathname()), true);
        $this->assertSame(['passed' => 0, 'attempted' => 1, 'selected' => 2], $report['summary']);
        $this->assertSame($before, $this->recordCounts());
    }

    public function test_grader_rejects_wrong_dates_missing_items_and_confidence_that_would_not_execute(): void
    {
        $case = ['text' => 'Call the plumber', 'expected' => [['type' => 'create_task', 'due_date' => '2026-10-09']]];
        $grader = app(ParserEvaluation::class);
        $this->assertFalse($grader->grade($case, ['actions' => [$this->action(['due_date' => '2026-10-10'])]])['passed']);
        $this->assertFalse($grader->grade($case, ['actions' => [$this->action(['due_date' => '2026-10-09', 'confidence' => .4])]])['passed']);
        $this->assertTrue($grader->grade($case, ['actions' => [$this->action(['due_date' => '2026-10-09'])]])['passed']);
        $case['text'] .= '. Buy milk.';
        $this->assertFalse($grader->grade($case, ['actions' => [$this->action(['due_date' => '2026-10-09'])]])['passed']);
    }

    public function test_fixtures_cover_scope_examples_and_date_boundaries(): void
    {
        $fixtures = app(ParserEvaluation::class)->fixtures();
        $ids = array_column($fixtures['cases'], 'id');
        $this->assertSame($ids, array_values(array_unique($ids)));
        foreach (['brain_dump', 'five_items', 'someday', 'calendar', 'waiting', 'ambiguous_completion', 'evening_timezone', 'spring_dst', 'fall_dst'] as $id) {
            $this->assertContains($id, $ids);
        }
        $this->assertCount(25, $fixtures['cases']);
    }
}
