<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureChartOwner;
use App\Http\Middleware\RequireTwoFactorAuthentication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChartAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_explicit_entry_routes_are_public_including_package_routes(): void
    {
        $expected = ['GET /', 'GET login', 'POST login', 'GET forgot-password', 'POST forgot-password', 'GET reset-password/{token}', 'POST reset-password', 'GET two-factor-challenge', 'POST two-factor-challenge', 'GET sanctum/csrf-cookie', 'GET up'];
        $actual = [];
        foreach (Route::getRoutes() as $route) {
            $middleware = $route->gatherMiddleware();
            if (in_array('auth', $middleware, true) || in_array('auth:web', $middleware, true)) {
                $this->assertContains(EnsureChartOwner::class, $middleware, $route->uri());

                continue;
            }
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $actual[] = $method.' '.$route->uri();
            }
        }
        sort($actual);
        sort($expected);
        $this->assertSame($expected, $actual);
    }

    public function test_landing_login_and_guest_boundary(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Home')->where('auth.user', null));
        $this->get('/login')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/user/profile')->assertRedirect('/login');
        $this->getJson('/dashboard')->assertUnauthorized();
        $this->get('/up')->assertOk()->assertExactJson(['status' => 'ok']);
    }

    public function test_only_configured_owner_can_login_even_with_valid_password(): void
    {
        User::factory()->create();
        $other = User::factory()->create();
        $this->post('/login', ['email' => $other->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($other)->get('/user/profile')->assertForbidden();
        $this->get('/dashboard')->assertForbidden();
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_missing_owner_configuration_fails_closed(): void
    {
        $owner = User::factory()->create();
        config(['chart.owner_id' => null]);
        $this->post('/login', ['email' => $owner->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->actingAs($owner)->get('/dashboard')->assertForbidden();
    }

    public function test_owner_must_confirm_two_factor_before_opening_chart(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->get('/dashboard')->assertRedirect('/user/profile');
        $this->get('/user/profile')->assertOk();
        $owner->forceFill(['two_factor_secret' => encrypt('secret')])->save();
        $this->get('/dashboard')->assertRedirect('/user/profile');
        $owner->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Dashboard'));
        $this->assertStringContainsString('no-store', $this->get('/dashboard')->headers->get('Cache-Control'));
        $this->get('/')->assertRedirect('/dashboard');
    }

    public function test_two_factor_login_requires_a_valid_code_or_recovery_code(): void
    {
        $owner = User::factory()->create([
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-one'])),
        ]);
        $this->post('/login', ['email' => $owner->email, 'password' => 'password'])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
        $this->post('/two-factor-challenge', ['recovery_code' => 'wrong'])->assertSessionHasErrors('recovery_code');
        $this->assertGuest();
        $this->post('/two-factor-challenge', ['recovery_code' => 'recovery-code-one'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($owner);
        $this->assertNotContains('recovery-code-one', $owner->fresh()->recoveryCodes());
    }

    public function test_retired_starter_routes_are_unavailable_to_guests_and_owner(): void
    {
        $paths = ['/blog', '/changelog', '/roadmap', '/coming-soon', '/sitemap', '/og-image', '/og-image-testing', '/admin/login', '/admin', '/terms-of-service', '/privacy-policy', '/teams/create', '/paddle/checkout/1', '/invoices/1/download', '/api/user'];
        foreach ($paths as $path) {
            $this->get($path)->assertNotFound();
        }
        $this->actingAs(User::factory()->create());
        foreach ($paths as $path) {
            $this->get($path)->assertNotFound();
        }
        foreach (['/stripe/webhook', '/lemon-squeezy/webhook', '/coming-soon'] as $path) {
            $this->post($path)->assertNotFound();
        }
    }

    public function test_package_endpoints_require_owner_authentication(): void
    {
        $this->get('/filament/exports/1/download')->assertRedirect('/login');
        $upload = Route::getRoutes()->getByName('livewire.upload-file');
        $this->post('/'.$upload->uri())->assertRedirect('/login');
        $this->assertContains(RequireTwoFactorAuthentication::class, $upload->gatherMiddleware());
    }

    public function test_profile_photo_upload_and_account_deletion_are_disabled(): void
    {
        $this->actingAs($owner = User::factory()->create());
        $this->put('/user/profile-information', ['name' => $owner->name, 'email' => $owner->email, 'photo' => 'not-allowed'])->assertSessionHasErrors('photo', null, 'updateProfileInformation');
        $this->delete('/user')->assertNotFound();
        $this->delete('/user/profile-photo')->assertNotFound();
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
    }
}
