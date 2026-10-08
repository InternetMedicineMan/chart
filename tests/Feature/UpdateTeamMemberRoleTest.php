<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;

class UpdateTeamMemberRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Jetstream::hasTeamFeatures()) {
            $this->markTestSkipped('Teams are disabled for single-owner Chart.');
        }
    }

    public function test_team_member_roles_can_be_updated(): void
    {
        $this->actingAs($user = User::factory()->withPersonalTeam()->create());

        $user->currentTeam->users()->attach(
            $otherUser = User::factory()->create(), ['role' => 'admin']
        );

        $response = $this->put('/teams/'.$user->currentTeam->id.'/members/'.$otherUser->id, [
            'role' => 'editor',
        ]);

        $this->assertTrue($otherUser->fresh()->hasTeamRole(
            $user->currentTeam->fresh(), 'editor'
        ));
    }

    public function test_only_team_owner_can_update_team_member_roles(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $user->currentTeam->users()->attach(
            $otherUser = User::factory()->create(), ['role' => 'admin']
        );

        $this->actingAs($otherUser);

        $response = $this->put('/teams/'.$user->currentTeam->id.'/members/'.$otherUser->id, [
            'role' => 'editor',
        ]);

        $this->assertTrue($otherUser->fresh()->hasTeamRole(
            $user->currentTeam->fresh(), 'admin'
        ));
    }
}
