<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end tests for the authentication API (Phase 1).
 * Runs against an in-memory SQLite database (see phpunit.xml).
 */
class AuthTest extends TestCase
{
    use RefreshDatabase; // builds a fresh database before each test

    public function test_a_customer_can_register_and_receive_a_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'John Customer',
            'email'                 => 'john@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'phone'                 => '111222333',
            'role'                  => 'customer',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'access_token',
                'token_type',
                'expires_in',
                'user' => ['id', 'name', 'email', 'role', 'avatar_url'],
            ])
            ->assertJsonPath('user.role', 'customer');

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'role'  => 'customer',
        ]);
    }

    public function test_registration_validation_fails_with_bad_data(): void
    {
        $this->postJson('/api/auth/register', [
            'name'     => '',
            'email'    => 'not-an-email',
            'password' => '123', // too short + not confirmed
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_a_user_can_login_and_reach_protected_routes(): void
    {
        // register a rider first
        $this->postJson('/api/auth/register', [
            'name'                  => 'Jane Rider',
            'email'                 => 'jane@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => 'rider',
        ])->assertStatus(201);

        // login
        $token = $this->postJson('/api/auth/login', [
            'email'    => 'jane@example.com',
            'password' => 'password123',
        ])->assertStatus(200)->json('access_token');

        $this->assertNotEmpty($token);

        // GET /me (protected)
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('user.email', 'jane@example.com');

        // logout (protected) -> token becomes invalid
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/logout')
            ->assertStatus(200);

        // the same token must no longer work
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/me')
            ->assertStatus(401);
    }

    public function test_token_can_be_refreshed(): void
    {
        $this->postJson('/api/auth/register', [
            'name'                  => 'Ray Refresh',
            'email'                 => 'ray@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(201);

        $token = $this->postJson('/api/auth/login', [
            'email'    => 'ray@example.com',
            'password' => 'password123',
        ])->json('access_token');

        $newToken = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/refresh-token')
            ->assertStatus(200)
            ->assertJsonStructure(['access_token'])
            ->json('access_token');

        // the new token works on a protected route
        $this->withHeader('Authorization', 'Bearer ' . $newToken)
            ->getJson('/api/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('user.email', 'ray@example.com');
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $this->postJson('/api/auth/register', [
            'name'                  => 'Bob Wrong',
            'email'                 => 'bob@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(201);

        $this->postJson('/api/auth/login', [
            'email'    => 'bob@example.com',
            'password' => 'totally-wrong',
        ])->assertStatus(401);
    }

    public function test_protected_route_requires_a_token(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401);
    }
}
