<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Tests that login works correctly when a user has email = null (Phase 2 fix).
 */
class CustomerLoginNullEmailTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'password' => bcrypt('secret123'),
            'is_phone_verified' => 1,
            'is_active' => 1,
        ], $overrides));
    }

    public function test_api_login_with_phone_when_email_is_null(): void
    {
        $user = $this->makeUser([
            'email' => null,
            'phone' => '+966500000001',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email_or_phone' => '+966500000001',
            'password' => 'secret123',
            'type' => 'phone',
        ]);

        $response->assertOk()->assertJsonStructure(['token']);
    }

    public function test_api_login_with_phone_and_email_present(): void
    {
        $user = $this->makeUser([
            'email' => 'test@example.com',
            'phone' => '+966500000002',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email_or_phone' => '+966500000002',
            'password' => 'secret123',
            'type' => 'phone',
        ]);

        $response->assertOk()->assertJsonStructure(['token']);
    }

    public function test_api_login_with_email_still_works(): void
    {
        $user = $this->makeUser([
            'email' => 'test2@example.com',
            'phone' => '+966500000003',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email_or_phone' => 'test2@example.com',
            'password' => 'secret123',
            'type' => 'email',
        ]);

        $response->assertOk()->assertJsonStructure(['token']);
    }

    public function test_api_login_wrong_password_returns_error(): void
    {
        $user = $this->makeUser([
            'email' => null,
            'phone' => '+966500000004',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email_or_phone' => '+966500000004',
            'password' => 'wrongpassword',
            'type' => 'phone',
        ]);

        $response->assertStatus(403);
    }

    public function test_api_login_wrong_password_with_email_returns_error(): void
    {
        $user = $this->makeUser([
            'email' => 'test3@example.com',
            'phone' => '+966500000005',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email_or_phone' => 'test3@example.com',
            'password' => 'wrongpassword',
            'type' => 'email',
        ]);

        $response->assertStatus(403);
    }

    public function test_hash_check_works_for_null_email_user(): void
    {
        $user = $this->makeUser(['email' => null]);

        $this->assertTrue(Hash::check('secret123', $user->password));
        $this->assertFalse(Hash::check('wrongpassword', $user->password));
    }
}
