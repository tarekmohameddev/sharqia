<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'f_name' => $this->faker->firstName,
            'l_name' => $this->faker->lastName,
            'email' => $this->faker->unique()->safeEmail,
            'phone' => $this->faker->unique()->numerify('+966#########'),
            'email_verified_at' => now(),
            'is_phone_verified' => 1,
            'is_email_verified' => 1,
            'is_active' => 1,
            'password' => bcrypt('password'),
            'remember_token' => Str::random(10),
            'referral_code' => Str::random(10),
            'registration_source' => 'web',
            'claimed_at' => now(),
        ];
    }

    /**
     * POS-created unclaimed account (no email, random password, not claimed).
     */
    public function posUnclaimed(): static
    {
        return $this->state(fn() => [
            'email' => null,
            'email_verified_at' => null,
            'is_email_verified' => 0,
            'password' => bcrypt(Str::random(32)),
            'registration_source' => 'pos',
            'claimed_at' => null,
        ]);
    }

    /**
     * User with no email (but claimed -- e.g. OTP or social login with no email).
     */
    public function noEmail(): static
    {
        return $this->state(fn() => [
            'email' => null,
            'email_verified_at' => null,
            'is_email_verified' => 0,
        ]);
    }

    /**
     * Already claimed user (normal mobile registration).
     */
    public function claimed(): static
    {
        return $this->state(fn() => [
            'registration_source' => 'mobile',
            'claimed_at' => now(),
        ]);
    }
}
