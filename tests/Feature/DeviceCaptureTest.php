<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Jobs\ParseCapture;
use App\Models\Capture;
use App\Models\CaptureItem;
use App\Models\CaptureToken;
use App\Models\User;
use App\Services\CaptureWaiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DeviceCaptureTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private CaptureToken $token;

    private string $plain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()]);
        config(['chart.owner_id' => $this->owner->id, 'chart.capture.enabled' => true, 'chart.capture.key' => 'test-only-key', 'chart.capture.connection' => 'database', 'inertia.ssr.enabled' => false]);
        $this->plain = 'ct_'.Str::random(64);
        $this->token = CaptureToken::factory()->create(['user_id' => $this->owner->id, 'token_hash' => hash('sha256', $this->plain)]);
        Bus::fake();
        Http::preventStrayRequests();
    }

    private function data(array $overrides = []): array
    {
        return array_replace(['text' => "  Call the plumber.\nAsk about the leak.  ", 'source' => 'ios', 'captured_at' => '2026-10-08T09:30:00-05:00'], $overrides);
    }

    public function test_owner_creates_a_hashed_write_only_token_revealed_only_in_the_creation_response(): void
    {
        $response = $this->actingAs($this->owner)->postJson('/settings/capture-tokens', [
            'label' => 'My watch', 'device_name' => 'Watch', 'scopes' => ['*'], 'user_id' => 999, 'rate_limit_per_hour' => 999999,
        ])->assertCreated()->assertJsonMissingPath('device.token_hash')->assertHeader('Cache-Control', 'no-store, private');
        $plain = $response->json('token');
        $this->assertMatchesRegularExpression('/\Act_[a-zA-Z0-9]{64}\z/', $plain);
        $token = CaptureToken::findOrFail($response->json('device.id'));
        $this->assertSame(hash('sha256', $plain), $token->token_hash);
        $this->assertSame(['capture:write'], $token->scopes);
        $this->assertSame(120, $token->rate_limit_per_hour);
        $this->assertSame($this->owner->id, $token->user_id);
        $this->get('/settings/work')->assertOk()->assertDontSee($plain)->assertDontSee($token->token_hash)
            ->assertInertia(fn (Assert $page) => $page->has('captureTokens', 2)->missing('captureTokens.0.token_hash')->has('captureEndpoint'));
        $this->assertStringNotContainsString($plain, json_encode(session()->all()));
    }

    public function test_token_management_requires_owner_and_confirmed_two_factor_and_valid_label(): void
    {
        $this->postJson('/settings/capture-tokens', ['label' => 'Watch'])->assertUnauthorized();
        $other = User::factory()->create(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()]);
        $this->actingAs($other)->postJson('/settings/capture-tokens', ['label' => 'Watch'])->assertForbidden();
        $this->owner->update(['two_factor_confirmed_at' => null]);
        $this->actingAs($this->owner)->postJson('/settings/capture-tokens', ['label' => 'Watch'])->assertForbidden();
        $this->owner->update(['two_factor_confirmed_at' => now()]);
        $this->postJson('/settings/capture-tokens', ['label' => ''])->assertUnprocessable();
        $foreign = CaptureToken::factory()->create(['user_id' => $other->id]);
        $this->deleteJson('/settings/capture-tokens/'.$foreign->id)->assertNotFound();
        $this->assertNull($foreign->fresh()->revoked_at);
    }

    public function test_bearer_capture_preserves_words_and_saves_before_dispatch_without_calling_ai(): void
    {
        $response = $this->withToken($this->plain)->postJson('/api/capture', $this->data(['device_label' => 'Jason’s iPhone']))
            ->assertAccepted()->assertJsonPath('spoken_confirmation', 'Saved. Sorting it now.')->assertHeader('Cache-Control', 'no-store, private');
        $capture = Capture::findOrFail($response->json('capture_id'));
        $this->assertSame($this->data()['text'], $capture->raw_text);
        $this->assertSame($this->token->id, $capture->capture_token_id);
        $this->assertSame('Jason’s iPhone', $capture->device_label);
        $this->assertSame($this->owner->id, $capture->user_id);
        $this->assertNotNull($this->token->fresh()->last_used_at);
        Bus::assertDispatched(ParseCapture::class, fn ($job) => $job->captureId === $capture->id && Capture::find($job->captureId) !== null);
        Http::assertNothingSent();
    }

    public function test_retries_deduplicate_normalized_timestamps_and_are_isolated_by_token(): void
    {
        $first = $this->withToken($this->plain)->postJson('/api/capture', $this->data())->assertAccepted()->json('capture_id');
        $this->postJson('/api/capture', $this->data(['captured_at' => '2026-10-08T14:30:00Z']))->assertAccepted()->assertJsonPath('capture_id', $first);
        $this->postJson('/api/capture', $this->data(['text' => 'Different words']))->assertAccepted();
        $secondPlain = 'ct_'.Str::random(64);
        CaptureToken::factory()->create(['user_id' => $this->owner->id, 'token_hash' => hash('sha256', $secondPlain)]);
        $this->withToken($secondPlain)->postJson('/api/capture', $this->data())->assertAccepted();
        $this->assertDatabaseCount('captures', 3);
        Bus::assertDispatchedTimes(ParseCapture::class, 3);
    }

    public function test_explicit_request_id_handles_missing_timestamp_and_rejects_changed_words_or_time(): void
    {
        $data = ['text' => 'Buy milk', 'source' => 'ios', 'request_key' => (string) Str::uuid()];
        $first = $this->withToken($this->plain)->postJson('/api/capture', $data)->assertAccepted()->json('capture_id');
        $this->travel(5)->minutes();
        $this->postJson('/api/capture', $data)->assertAccepted()->assertJsonPath('capture_id', $first);
        $this->postJson('/api/capture', array_replace($data, ['text' => 'Buy bread']))->assertUnprocessable();
        $this->postJson('/api/capture', array_replace($data, ['captured_at' => '2020-01-01T00:00:00Z']))->assertUnprocessable();
        $this->assertDatabaseCount('captures', 1);
    }

    public function test_optional_timestamp_and_id_do_not_accidentally_deduplicate_new_captures(): void
    {
        $data = ['text' => 'Buy milk', 'source' => 'ios'];
        $this->withToken($this->plain)->postJson('/api/capture', $data)->assertAccepted();
        $this->postJson('/api/capture', $data)->assertAccepted();
        $this->assertDatabaseCount('captures', 2);
    }

    public function test_invalid_and_revoked_tokens_fail_closed_even_with_an_authenticated_session(): void
    {
        $this->post('/api/capture', $this->data())->assertUnauthorized()->assertHeader('Content-Type', 'application/json')->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($this->owner)->withToken('ct_'.Str::random(64))->postJson('/api/capture', $this->data())->assertUnauthorized();
        $this->withToken($this->plain)->postJson('/api/capture', $this->data())->assertAccepted();
        $this->deleteJson('/settings/capture-tokens/'.$this->token->id)->assertOk();
        $this->deleteJson('/settings/capture-tokens/'.$this->token->id)->assertOk();
        $this->postJson('/api/capture', $this->data())->assertUnauthorized();
        $this->assertDatabaseCount('captures', 1);
        $this->assertNotNull($this->token->fresh()->revoked_at);
    }

    public function test_tokens_require_scope_configured_owner_and_confirmed_two_factor(): void
    {
        $this->withToken($this->plain);
        $this->token->update(['scopes' => ['account:read']]);
        $this->postJson('/api/capture', $this->data())->assertUnauthorized();
        $this->token->update(['scopes' => ['capture:write']]);
        config(['chart.owner_id' => null]);
        $this->postJson('/api/capture', $this->data())->assertForbidden();
        config(['chart.owner_id' => $this->owner->id + 1]);
        $this->postJson('/api/capture', $this->data())->assertForbidden();
        config(['chart.owner_id' => $this->owner->id]);
        $this->owner->update(['two_factor_confirmed_at' => null]);
        $this->postJson('/api/capture', $this->data())->assertForbidden();
        $this->assertDatabaseCount('captures', 0);
    }

    public function test_capture_tokens_cannot_read_or_manage_the_account(): void
    {
        $this->withToken($this->plain)->postJson('/api/capture', $this->data())->assertAccepted();
        foreach (['/settings/work', '/intake', '/captures/1', '/dashboard'] as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
        $this->postJson('/settings/capture-tokens', ['label' => 'Bad'])->assertUnauthorized();
        $this->deleteJson('/settings/capture-tokens/'.$this->token->id)->assertUnauthorized();
    }

    public function test_session_capture_enforces_csrf_instead_of_exempting_the_whole_route(): void
    {
        $this->app->bind(VerifyCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends VerifyCsrfToken
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
        $this->actingAs($this->owner)->withSession(['_token' => 'test-csrf']);
        $this->postJson('/api/capture', $this->data())->assertStatus(419);
        $this->withHeader('X-CSRF-TOKEN', 'test-csrf')->postJson('/api/capture', $this->data())->assertAccepted();
        $this->assertNull(Capture::first()->capture_token_id);
        $this->owner->update(['two_factor_confirmed_at' => null]);
        $this->postJson('/api/capture', $this->data())->assertForbidden();
    }

    public function test_validation_fails_before_storage_and_does_not_allow_owner_or_token_injection(): void
    {
        $this->withToken($this->plain);
        foreach ([['text' => '  '], ['text' => str_repeat('a', 20001)], ['source' => 'email'], ['captured_at' => 'tomorrow'], ['device_label' => str_repeat('a', 101)], ['mode' => 'delete'], ['request_key' => 'bad'], ['wait' => 'forever']] as $invalid) {
            $this->postJson('/api/capture', $this->data($invalid))->assertUnprocessable();
        }
        $this->assertDatabaseCount('captures', 0);
        $this->postJson('/api/capture', $this->data(['user_id' => 999, 'capture_token_id' => 999, 'status' => 'executed']))->assertAccepted();
        $this->assertSame($this->owner->id, Capture::first()->user_id);
        $this->assertSame($this->token->id, Capture::first()->capture_token_id);
        $this->assertSame('received', Capture::first()->status);
    }

    public function test_token_hourly_limit_rejects_before_saving_and_returns_retry_after(): void
    {
        $this->token->update(['rate_limit_per_hour' => 1]);
        $this->withToken($this->plain)->postJson('/api/capture', $this->data())->assertAccepted();
        $this->post('/api/capture', $this->data(['text' => 'Another task']))->assertTooManyRequests()->assertHeader('Retry-After')->assertHeader('Content-Type', 'application/json')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseCount('captures', 1);
        $this->travel(61)->minutes();
        $this->postJson('/api/capture', $this->data(['text' => 'Another task']))->assertAccepted();
    }

    public function test_wait_stops_at_its_deadline_without_parsing_in_the_request(): void
    {
        $waiter = $this->fakeWaiter();
        $this->app->instance(CaptureWaiter::class, $waiter);
        $this->withToken($this->plain)->postJson('/api/capture?wait=1', $this->data())
            ->assertAccepted()->assertJsonPath('spoken_confirmation', 'Saved. Sorting it now.');
        $this->assertEqualsWithDelta(8, $waiter->clock, 0.00001);
        $this->assertDatabaseCount('capture_attempts', 0);
        Http::assertNothingSent();
    }

    public function test_wait_returns_completed_outcomes_as_soon_as_the_worker_finishes(): void
    {
        $waiter = $this->fakeWaiter(function () {
            $capture = Capture::firstOrFail();
            CaptureItem::create(['user_id' => $capture->user_id, 'capture_id' => $capture->id, 'sequence' => 0, 'excerpt' => $capture->raw_text, 'action_type' => 'create_task', 'status' => 'executed', 'payload' => []]);
            $capture->update(['status' => 'executed']);
        });
        $this->app->instance(CaptureWaiter::class, $waiter);
        $this->withToken($this->plain)->postJson('/api/capture?wait=1', $this->data())->assertAccepted()
            ->assertJsonPath('status', 'executed')->assertJsonPath('item_count', 1)->assertJsonPath('spoken_confirmation', 'Saved. 1 filed.');
        $this->assertSame(0.2, $waiter->clock);
    }

    public function test_disabled_sorting_returns_durable_inbox_confirmation_without_waiting(): void
    {
        config(['chart.capture.enabled' => false]);
        $waiter = $this->fakeWaiter();
        $this->app->instance(CaptureWaiter::class, $waiter);
        $this->withToken($this->plain)->postJson('/api/capture?wait=1', $this->data())->assertAccepted()
            ->assertJsonPath('spoken_confirmation', 'Saved to Inbox. Automatic sorting needs attention.');
        $this->assertSame(0.0, $waiter->clock);
        $this->assertDatabaseCount('tasks', 1);
        Bus::assertNothingDispatched();
    }

    public function test_worker_files_a_device_capture_and_a_retry_returns_the_existing_outcome(): void
    {
        Bus::fake()->except([ParseCapture::class]);
        $data = $this->data(['text' => 'Call the plumber']);
        $id = $this->withToken($this->plain)->postJson('/api/capture', $data)->assertAccepted()->json('capture_id');
        Http::fake(['https://api.openai.com/v1/responses' => Http::response([
            'status' => 'completed', 'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode(['actions' => [
                ['type' => 'create_task', 'confidence' => .95, 'excerpt' => 'Call the plumber', 'title' => 'Call the plumber'],
            ]])]]]],
        ])]);
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'captures', '--once' => true, '--stop-when-empty' => true])->assertSuccessful();
        $this->postJson('/api/capture?wait=1', $data)->assertAccepted()->assertJsonPath('capture_id', $id)
            ->assertJsonPath('status', 'executed')->assertJsonPath('spoken_confirmation', 'Saved. 1 filed.');
        $this->assertDatabaseCount('tasks', 1);
        $this->assertDatabaseCount('jobs', 0);
        Http::assertSentCount(1);
    }

    public function test_pending_items_are_not_misreported_as_needing_a_decision(): void
    {
        $id = $this->withToken($this->plain)->postJson('/api/capture', $this->data())->assertAccepted()->json('capture_id');
        CaptureItem::create(['user_id' => $this->owner->id, 'capture_id' => $id, 'sequence' => 0, 'excerpt' => 'Call the plumber', 'action_type' => 'create_task', 'status' => 'pending', 'payload' => []]);
        $this->postJson('/api/capture', $this->data())->assertAccepted()->assertJsonPath('spoken_confirmation', 'Saved. Sorting it now.');
    }

    private function fakeWaiter(?\Closure $onPause = null): CaptureWaiter
    {
        return new class($onPause) extends CaptureWaiter
        {
            public float $clock = 0;

            public function __construct(private ?\Closure $onPause) {}

            public function time(): float
            {
                return $this->clock;
            }

            protected function pause(int $microseconds): void
            {
                $this->clock += max(1, $microseconds) / 1000000;
                if ($this->onPause) {
                    ($this->onPause)();
                }
            }
        };
    }
}
