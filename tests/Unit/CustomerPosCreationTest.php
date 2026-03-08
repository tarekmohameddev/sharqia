<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Unit-level tests for POS user creation logic (Phases 3 and 7).
 */
class CustomerPosCreationTest extends TestCase
{
    use RefreshDatabase;

    private function createPosUser(string $phone = '+966500002001'): User
    {
        return User::create([
            'f_name' => 'POS',
            'l_name' => 'Customer',
            'email' => null,
            'phone' => $phone,
            'password' => bcrypt(Str::random(32)),
            'registration_source' => 'pos',
            'claimed_at' => null,
            'is_active' => 1,
        ]);
    }

    public function test_pos_created_user_has_registration_source_pos(): void
    {
        $user = $this->createPosUser();

        $this->assertEquals('pos', $user->registration_source);
    }

    public function test_pos_created_user_has_null_claimed_at(): void
    {
        $user = $this->createPosUser();

        $this->assertNull($user->claimed_at);
    }

    public function test_pos_created_user_has_random_password_not_123456(): void
    {
        $user = $this->createPosUser();

        $this->assertFalse(Hash::check('123456', $user->password),
            'POS user should NOT have 123456 as password');
    }

    public function test_pos_created_user_has_null_email(): void
    {
        $user = $this->createPosUser();

        $this->assertNull($user->email);
    }

    public function test_pos_does_not_create_duplicate_for_existing_phone(): void
    {
        $phone = '+966500002002';
        $first = $this->createPosUser($phone);

        // Simulate second POS order for same phone using updateOrCreate pattern
        $second = User::updateOrCreate(
            ['phone' => $phone],
            ['f_name' => 'Updated Name', 'l_name' => '']
        );

        // Should be the same row
        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(1, User::where('phone', $phone)->count());
    }

    public function test_easyorders_created_user_has_registration_source_import(): void
    {
        $user = User::create([
            'f_name' => 'Import',
            'l_name' => 'User',
            'email' => null,
            'phone' => '+966500002003',
            'password' => bcrypt(Str::random(32)),
            'registration_source' => 'import',
            'claimed_at' => null,
            'is_active' => 1,
        ]);

        $this->assertEquals('import', $user->registration_source);
        $this->assertNull($user->claimed_at);
    }

    public function test_self_registered_user_has_claimed_at_set(): void
    {
        $user = User::factory()->claimed()->create([
            'phone' => '+966500002004',
        ]);

        $this->assertNotNull($user->claimed_at);
        $this->assertEquals('mobile', $user->registration_source);
    }

    public function test_pos_updateorcreate_wasrecentlycreated_sets_source(): void
    {
        $phone = '+966500002005';

        // First call: creates the user
        $customer = User::updateOrCreate(
            ['phone' => $phone],
            ['f_name' => 'POS Create', 'l_name' => '']
        );

        if ($customer->wasRecentlyCreated) {
            $customer->update([
                'email' => null,
                'password' => bcrypt(Str::random(32)),
                'registration_source' => 'pos',
                'claimed_at' => null,
                'is_active' => 1,
            ]);
        }

        $customer->refresh();
        $this->assertEquals('pos', $customer->registration_source);
        $this->assertNull($customer->claimed_at);

        // Second call: updates existing user (wasRecentlyCreated = false)
        $customer2 = User::updateOrCreate(
            ['phone' => $phone],
            ['f_name' => 'POS Update', 'l_name' => '']
        );

        $this->assertFalse($customer2->wasRecentlyCreated);
        // registration_source should still be 'pos' (not overwritten)
        $customer2->refresh();
        $this->assertEquals('pos', $customer2->registration_source);
    }

    public function test_migration_backfill_marks_email_users_as_claimed(): void
    {
        // Create a user with email directly (bypassing factory to control state)
        $user = User::create([
            'f_name' => 'Web',
            'l_name' => 'User',
            'email' => 'webuser@example.com',
            'phone' => '+966500002006',
            'password' => bcrypt('password'),
            'registration_source' => null,
            'claimed_at' => null,
            'is_active' => 1,
        ]);

        // Simulate backfill logic from the migration
        User::whereNotNull('email')->whereNull('claimed_at')->update([
            'registration_source' => 'web',
            'claimed_at' => \Illuminate\Support\Facades\DB::raw('created_at'),
        ]);

        $user->refresh();
        $this->assertEquals('web', $user->registration_source);
        $this->assertNotNull($user->claimed_at);
    }
}
