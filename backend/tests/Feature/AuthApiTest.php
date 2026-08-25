<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_a_token_and_sanitized_user_resource(): void
    {
        $user = User::factory()->create([
            'email' => 'operator@example.com',
            'password' => Hash::make('password'),
        ]);
        $role = Role::query()->create([
            'name' => 'Administrator',
            'slug' => 'administrator',
        ]);
        $permission = Permission::query()->create([
            'name' => 'View dashboards',
            'slug' => 'dashboard.view',
        ]);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'operator@example.com',
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Authenticated successfully.')
            ->assertJsonPath('user.email', 'operator@example.com')
            ->assertJsonPath('user.roles.0', 'administrator')
            ->assertJsonMissingPath('user.password')
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'roles', 'permissions']]);
    }

    public function test_invalid_login_is_rejected_by_validation_or_credentials(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'not-an-email',
            'password' => 'short',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_is_rate_limited_per_ip_and_email(): void
    {
        RateLimiter::clear('127.0.0.1|brute@example.com');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/auth/login', [
                'email' => 'brute@example.com',
                'password' => 'password',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/auth/login', [
            'email' => 'brute@example.com',
            'password' => 'password',
        ])->assertStatus(429);
    }

    public function test_protected_api_access_requires_authentication_and_permission(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();

        $user = User::factory()->create();
        $token = $user->createToken('test-token', ['dashboard.view'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/auth/access-check')
            ->assertForbidden();

        $role = Role::query()->create([
            'name' => 'Administrator',
            'slug' => 'administrator',
        ]);
        $permission = Permission::query()->create([
            'name' => 'View dashboards',
            'slug' => 'dashboard.view',
        ]);
        $role->permissions()->attach($permission);
        $user->roles()->attach($role);

        $this->withToken($token)
            ->getJson('/api/auth/access-check')
            ->assertOk()
            ->assertJsonPath('authorized', true);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
        app('auth')->forgetGuards();
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }
}
