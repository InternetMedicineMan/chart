<?php

use App\Models\Roadmap;
use App\Models\RoadmapVote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('displays the roadmap page to guests', function () {
    $user = User::factory()->create();
    Roadmap::factory()->count(3)->create(['user_id' => $user->id]);

    $response = $this->get(route('roadmap.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Roadmap/Index')
        ->has('roadmaps', 3)
    );
});

it('allows authenticated users to create a feature request', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('roadmap.store'), [
            'title' => 'New Feature',
            'description' => 'This is a new feature request',
        ])
        ->assertRedirect(route('roadmap.index'));

    $this->assertDatabaseHas('roadmaps', [
        'user_id' => $user->id,
        'title' => 'New Feature',
        'description' => 'This is a new feature request',
        'status' => 'pending',
    ]);
});

it('requires authentication to create a feature request', function () {
    $this->post(route('roadmap.store'), [
        'title' => 'New Feature',
        'description' => 'This is a new feature request',
    ])->assertRedirect(route('login'));
});

it('validates feature request fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('roadmap.store'), [
            'title' => '',
            'description' => '',
        ])
        ->assertSessionHasErrors(['title', 'description']);
});

it('allows authenticated users to vote on a feature', function () {
    $user = User::factory()->create();
    $roadmap = Roadmap::factory()->create(['votes_count' => 0]);

    $this->actingAs($user)
        ->post(route('roadmap.vote', $roadmap))
        ->assertRedirect();

    $this->assertDatabaseHas('roadmap_votes', [
        'user_id' => $user->id,
        'roadmap_id' => $roadmap->id,
    ]);

    expect($roadmap->fresh()->votes_count)->toBe(1);
});

it('allows authenticated users to unvote a feature', function () {
    $user = User::factory()->create();
    $roadmap = Roadmap::factory()->create(['votes_count' => 1]);
    RoadmapVote::create([
        'user_id' => $user->id,
        'roadmap_id' => $roadmap->id,
    ]);

    $this->actingAs($user)
        ->post(route('roadmap.vote', $roadmap))
        ->assertRedirect();

    expect($roadmap->fresh()->votes_count)->toBe(0);
    expect(RoadmapVote::where('user_id', $user->id)->where('roadmap_id', $roadmap->id)->exists())->toBeFalse();
});

it('requires authentication to vote on a feature', function () {
    $roadmap = Roadmap::factory()->create();

    $this->post(route('roadmap.vote', $roadmap))
        ->assertRedirect(route('login'));
});

it('prevents duplicate votes from the same user', function () {
    $user = User::factory()->create();
    $roadmap = Roadmap::factory()->create();

    RoadmapVote::create([
        'user_id' => $user->id,
        'roadmap_id' => $roadmap->id,
    ]);

    $this->expectException(\Illuminate\Database\QueryException::class);

    RoadmapVote::create([
        'user_id' => $user->id,
        'roadmap_id' => $roadmap->id,
    ]);
});

it('shows roadmaps ordered by votes', function () {
    $user = User::factory()->create();
    $roadmap1 = Roadmap::factory()->create(['votes_count' => 5, 'user_id' => $user->id]);
    $roadmap2 = Roadmap::factory()->create(['votes_count' => 10, 'user_id' => $user->id]);
    $roadmap3 = Roadmap::factory()->create(['votes_count' => 2, 'user_id' => $user->id]);

    $response = $this->get(route('roadmap.index'));

    $response->assertInertia(fn ($page) => $page
        ->component('Roadmap/Index')
        ->where('roadmaps.0.id', $roadmap2->id)
        ->where('roadmaps.1.id', $roadmap1->id)
        ->where('roadmaps.2.id', $roadmap3->id)
    );
});

it('marks roadmaps as voted by authenticated user', function () {
    $user = User::factory()->create();
    $roadmap1 = Roadmap::factory()->create(['user_id' => $user->id, 'votes_count' => 10]);
    $roadmap2 = Roadmap::factory()->create(['user_id' => $user->id, 'votes_count' => 5]);

    RoadmapVote::create([
        'user_id' => $user->id,
        'roadmap_id' => $roadmap1->id,
    ]);

    $response = $this->actingAs($user)->get(route('roadmap.index'));

    $response->assertInertia(fn ($page) => $page
        ->component('Roadmap/Index')
        ->where('roadmaps.0.has_voted', true)
        ->where('roadmaps.1.has_voted', false)
    );
});
