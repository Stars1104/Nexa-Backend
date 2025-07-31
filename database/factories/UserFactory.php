<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => fake()->randomElement(['creator', 'brand']),
            'whatsapp' => fake()->optional()->numerify('+############'),
            'avatar_url' => fake()->optional()->imageUrl(200, 200),
            'bio' => fake()->optional()->paragraph(3),
            'company_name' => fake()->optional()->company(),
            'student_verified' => false,
            'student_expires_at' => null,
            'gender' => fake()->optional()->randomElement(['male', 'female', 'other']),
            'state' => fake()->optional()->state(),
            'language' => fake()->randomElement(['en', 'es', 'fr', 'de']),
            'has_premium' => false,
            'premium_expires_at' => null,
            'free_trial_expires_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Create a user with premium status.
     */
    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'has_premium' => true,
            'premium_expires_at' => now()->addYear(),
        ]);
    }

    /**
     * Create a user with trial status.
     */
    public function trial(): static
    {
        return $this->state(fn (array $attributes) => [
            'has_premium' => false,
            'free_trial_expires_at' => now()->addDays(30),
        ]);
    }

    /**
     * Create a user with admin role.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ]);
    }

    /**
     * Create a user with verified student status.
     */
    public function studentVerified(): static
    {
        return $this->state(fn (array $attributes) => [
            'student_verified' => true,
            'student_expires_at' => now()->addYear(),
        ]);
    }
}
