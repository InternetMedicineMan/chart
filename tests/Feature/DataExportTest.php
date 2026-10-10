<?php

use App\Models\CalendarConnection;
use App\Models\PushSubscription;
use App\Models\Task;
use App\Models\User;
use App\Services\WorkSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exports owned work including deleted records without credentials', function () {
    $owner = User::factory()->create(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()]);
    config(['chart.owner_id' => $owner->id]);
    $inbox = app(WorkSetup::class)->inbox($owner);
    $task = Task::create(['user_id' => $owner->id, 'domain_id' => $inbox->id, 'title' => 'Keep my original work']);
    $task->delete();
    $other = User::factory()->create();
    Task::create(['user_id' => $other->id, 'domain_id' => app(WorkSetup::class)->inbox($other)->id, 'title' => 'Other owner secret']);
    CalendarConnection::factory()->create(['user_id' => $owner->id, 'access_token' => 'DO-NOT-EXPORT-TOKEN']);
    PushSubscription::factory()->create(['user_id' => $owner->id]);
    $response = $this->actingAs($owner)->get(route('work.export'))->assertOk()->assertHeader('content-type', 'application/json');
    $content = $response->streamedContent();
    $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    expect($data['data']['tasks'])->toHaveCount(1)->and($data['data']['tasks'][0]['deleted_at'])->not->toBeNull();
    expect($content)->toContain('Keep my original work')->not->toContain('Other owner secret', 'DO-NOT-EXPORT-TOKEN', 'push_subscriptions', 'two_factor');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('requires owner authentication and two factor for export', function () {
    $owner = User::factory()->create();
    config(['chart.owner_id' => $owner->id]);
    $this->get(route('work.export'))->assertRedirect(route('login'));
    $this->actingAs($owner)->get(route('work.export'))->assertRedirect(route('profile.show'));
    $this->actingAs(User::factory()->create())->get(route('work.export'))->assertForbidden();
});
