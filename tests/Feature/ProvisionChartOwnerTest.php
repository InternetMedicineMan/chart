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
    }
}
