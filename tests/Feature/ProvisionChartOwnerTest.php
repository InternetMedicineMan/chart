<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProvisionChartOwnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_owner_is_preserved(): void
    {
        $user = User::factory()->create();
        $original = $user->fresh()->getAttributes();
        $this->artisan('chart:owner', ['email' => $user->email])->assertSuccessful();
        $this->assertSame($original, $user->fresh()->getAttributes());
        $this->assertDatabaseCount('users', 1);
    }

    public function test_locked_owner_creation_is_idempotent(): void
    {
        config(['chart.owner_id' => null]);
        $arguments = ['email' => 'owner@example.com', '--name' => 'Owner', '--create-locked' => true];
        $this->artisan('chart:owner', $arguments)->assertSuccessful();
        $hash = User::first()->password;
        $this->artisan('chart:owner', $arguments)->assertSuccessful();
        $this->assertDatabaseCount('users', 1);
        $this->assertSame($hash, User::first()->password);
        $this->assertFalse(Hash::check('password', $hash));
    }

    public function test_configured_owner_cannot_be_replaced_by_provisioning_another_account(): void
    {
        User::factory()->create();
        $this->artisan('chart:owner', ['email' => 'other@example.com', '--name' => 'Other', '--create-locked' => true])->assertFailed();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_password_is_set_only_with_explicit_option_and_confirmation(): void
    {
        $owner = User::factory()->create();
        $this->artisan('chart:owner', ['email' => $owner->email, '--set-password' => true])
            ->expectsQuestion('New password (at least 12 characters)', 'local-test-password')
            ->expectsQuestion('Confirm password', 'local-test-password')
            ->assertSuccessful();
        $this->assertTrue(Hash::check('local-test-password', $owner->fresh()->password));
        $this->assertNull($owner->fresh()->remember_token);

        $this->post('/login', ['email' => $owner->email, 'password' => 'local-test-password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($owner);
    }

    public function test_check_explains_missing_deployment_configuration_without_changing_account(): void
    {
        $owner = User::factory()->create();
        $original = $owner->fresh()->getAttributes();
        config(['chart.owner_id' => null]);

        $this->artisan('chart:owner', ['email' => $owner->email, '--check' => true])
            ->expectsOutput('Configured CHART_OWNER_ID: (not set)')
            ->expectsOutput('Account ID in this database: '.$owner->id)
            ->expectsOutput('Sign-in is blocked by the owner configuration, regardless of the password.')
            ->assertFailed();

        $this->assertSame($original, $owner->fresh()->getAttributes());
    }

    public function test_check_reports_mismatched_owner_without_transferring_access(): void
    {
        User::factory()->create();
        $other = User::factory()->create();
        $this->artisan('chart:owner', ['email' => $other->email, '--check' => true])
            ->expectsOutput('Configured CHART_OWNER_ID: 1')
            ->expectsOutput('Account ID in this database: '.$other->id)
            ->assertFailed();
        $this->assertEquals(1, config('chart.owner_id'));
    }

    public function test_check_never_provisions_a_missing_account(): void
    {
        $this->artisan('chart:owner', ['email' => 'absent@example.com', '--check' => true])
            ->expectsOutput('This email has no account in this environment. Local and deployed databases are separate.')
            ->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_check_reports_enrollment_needed_for_a_correctly_configured_owner(): void
    {
        $owner = User::factory()->create();
        $this->artisan('chart:owner', ['email' => $owner->email, '--check' => true])
            ->expectsOutput('Owner configuration matches this account.')
            ->expectsOutput('After signing in, enable two-factor authentication and confirm the code on Settings to unlock Chart.')
            ->assertSuccessful();
    }
}
