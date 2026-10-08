<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_unavailable_and_does_not_create_users(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'Stranger', 'email' => 'stranger@example.com', 'password' => 'password', 'password_confirmation' => 'password'])->assertNotFound();
        $this->post('/auth/magic-link', ['email' => 'stranger@example.com'])->assertNotFound();
        $this->get('/auth/redirect/google')->assertNotFound();
        $this->get('/auth/callback/google')->assertNotFound();
        $this->get('/auth/magic-link/example')->assertNotFound();
        $this->assertSame(0, User::count());
        $this->assertGuest();
    }
}
