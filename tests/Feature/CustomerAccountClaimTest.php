<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PhoneOrEmailVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tests the account claiming flow (Phase 4).
 * A POS-created user with claimed_at = null can be claimed via mobile registration.
 */
class CustomerAccountClaimTest extends TestCase
{
    use RefreshDatabase;

    private function posUser(string $phone = '+966511111111'): User
    {
        return User::factory()->posUnclaimed()->create([
            'phone' => $phone,
            'f_name' => 'POS',
            'l_name' => 'User',
        ]);
    }

    private function claimedUser(string $phone = '+966522222222'): User
    {
        return User::factory()->claimed()->create([
            'phone' => $phone,
            'email' => 'existing@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_register_with_existing_unclaimed_phone_returns_claim_flow(): void
    {
        $this->posUser('+966533333333');

        $response = $this->postJson('/api/v1/auth/register', [
            'f_name' => 'Ahmed',
            'l_name' => 'Ali',
            'phone' => '+966533333333',
            'password' => 'newpassword123',
        ]);

        $response->assertOk()
            ->assertJson(['claim_account' => true, 'status' => false])
            ->assertJsonStructure(['temporary_token', 'phone']);
    }

    public function test_register_with_existing_claimed_phone_returns_error(): void
    {
        $this->claimedUser('+966544444444');

        $response = $this->postJson('/api/v1/auth/register', [
            'f_name' => 'Ahmed',
            'l_name' => 'Ali',
            'phone' => '+966544444444',
            'password' => 'newpassword123',
        ]);

        $response->assertStatus(403)
            ->assertJsonFragment(['code' => 'phone']);
    }

    public function test_register_with_new_phone_creates_user_normally(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'f_name' => 'New',
            'l_name' => 'User',
            'phone' => '+966555555551',
            'password' => 'password123',
        ]);

        // Phone verification may be required; either token or direct token
        $response->assertOk();

        $user = User::where('phone', '+966555555551')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->claimed_at);
        $this->assertEquals('mobile', $user->registration_source);
    }

    public function test_otp_registration_claims_unclaimed_account(): void
    {
        $pos = $this->posUser('+966566666666');

        $response = $this->postJson('/api/v1/auth/registration-with-otp', [
            'name' => 'Ahmed Ali',
            'phone' => '+966566666666',
        ]);

        $response->assertOk()->assertJsonStructure(['token']);

        $pos->refresh();
        $this->assertNotNull($pos->claimed_at);
        $this->assertEquals('otp', $pos->registration_source);
        $this->assertEquals(1, $pos->is_phone_verified);
        // Only one user row should exist
        $this->assertEquals(1, User::where('phone', '+966566666666')->count());
    }

    public function test_otp_registration_with_claimed_phone_returns_error(): void
    {
        $this->claimedUser('+966577777777');

        $response = $this->postJson('/api/v1/auth/registration-with-otp', [
            'name' => 'Someone',
            'phone' => '+966577777777',
        ]);

        $response->assertStatus(403);
    }

    public function test_otp_registration_with_new_phone_creates_user(): void
    {
        $response = $this->postJson('/api/v1/auth/registration-with-otp', [
            'name' => 'Brand New',
            'phone' => '+966588888888',
        ]);

        $response->assertOk()->assertJsonStructure(['token']);

        $user = User::where('phone', '+966588888888')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->claimed_at);
        $this->assertEquals('otp', $user->registration_source);
    }

    public function test_pos_update_does_not_overwrite_claimed_account_password(): void
    {
        // User has already claimed their account with a known password
        $user = User::factory()->claimed()->create([
            'phone' => '+966599999999',
            'password' => bcrypt('myknownpassword'),
            'f_name' => 'Original',
        ]);

        // Simulate POS creating an order for same phone (updateOrCreate)
        $customer = \App\Models\User::updateOrCreate(
            ['phone' => '+966599999999'],
            ['f_name' => 'From POS', 'l_name' => '']
        );

        if ($customer->wasRecentlyCreated) {
            $customer->update([
                'email' => null,
                'password' => bcrypt(\Illuminate\Support\Str::random(32)),
                'registration_source' => 'pos',
                'claimed_at' => null,
                'is_active' => 1,
            ]);
        }

        $user->refresh();
        // Password should not have been changed since wasRecentlyCreated = false
        $this->assertTrue(Hash::check('myknownpassword', $user->password));
        // claimed_at should still be set
        $this->assertNotNull($user->claimed_at);
        // Name may be updated by POS (updateOrCreate updates all columns in the value array)
        // The key thing is password and claimed_at are preserved
    }

    public function test_concurrent_claim_only_one_succeeds(): void
    {
        $pos = $this->posUser('+966500000099');

        // Simulate two concurrent claim attempts by directly calling the atomic update twice
        $affected1 = User::where('phone', '+966500000099')
            ->whereNull('claimed_at')
            ->update(['claimed_at' => now(), 'f_name' => 'First Claimer']);

        $affected2 = User::where('phone', '+966500000099')
            ->whereNull('claimed_at')
            ->update(['claimed_at' => now(), 'f_name' => 'Second Claimer']);

        // First succeeds, second gets 0 affected rows
        $this->assertEquals(1, $affected1);
        $this->assertEquals(0, $affected2);

        // The account name should be from the first claimer
        $pos->refresh();
        $this->assertEquals('First Claimer', $pos->f_name);
        $this->assertNotNull($pos->claimed_at);
    }
}
