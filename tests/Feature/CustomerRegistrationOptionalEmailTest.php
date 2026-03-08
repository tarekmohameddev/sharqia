<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests that email is optional in registration endpoints (Phase 5).
 */
class CustomerRegistrationOptionalEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_register_without_email_succeeds(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'f_name' => 'Ahmed',
            'l_name' => 'Ali',
            'phone' => '+966500001001',
            'password' => 'password123',
        ]);

        // Should not get a 422 validation error for missing email
        $this->assertNotEquals(422, $response->status(), 'Should not require email: ' . $response->getContent());

        $user = User::where('phone', '+966500001001')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email);
        $this->assertNotNull($user->claimed_at);
    }

    public function test_api_register_with_email_still_works(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'f_name' => 'Sara',
            'l_name' => 'Al',
            'email' => 'sara@example.com',
            'phone' => '+966500001002',
            'password' => 'password123',
        ]);

        $this->assertNotEquals(422, $response->status());

        $user = User::where('phone', '+966500001002')->first();
        $this->assertNotNull($user);
        $this->assertEquals('sara@example.com', $user->email);
    }

    public function test_api_register_with_duplicate_email_returns_error(): void
    {
        User::factory()->create(['email' => 'duplicate@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'f_name' => 'Ahmed',
            'l_name' => 'Ali',
            'email' => 'duplicate@example.com',
            'phone' => '+966500001003',
            'password' => 'password123',
        ]);

        $response->assertStatus(403);
    }

    public function test_passport_register_without_email_succeeds(): void
    {
        $response = $this->postJson('/api/v1/auth/register-with-passport', [
            'f_name' => 'Khalid',
            'l_name' => 'Omar',
            'phone' => '+966500001004',
            'password' => 'password12345',
        ]);

        // Should not be a 422 validation error
        $this->assertNotEquals(422, $response->status(), 'Passport register should allow no email: ' . $response->getContent());
    }

    public function test_otp_registration_without_email_creates_user(): void
    {
        $response = $this->postJson('/api/v1/auth/registration-with-otp', [
            'name' => 'Fatima Ali',
            'phone' => '+966500001005',
        ]);

        $response->assertOk()->assertJsonStructure(['token']);

        $user = User::where('phone', '+966500001005')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email);
        $this->assertNotNull($user->claimed_at);
    }

    public function test_registration_endpoint_without_email_succeeds(): void
    {
        $response = $this->postJson('/api/v1/auth/registration', [
            'f_name' => 'Omar',
            'l_name' => 'Hassan',
            'phone' => '+966500001006',
            'password' => 'password123',
        ]);

        $this->assertNotEquals(422, $response->status(), 'Should not require email: ' . $response->getContent());

        $user = User::where('phone', '+966500001006')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email);
    }
}
