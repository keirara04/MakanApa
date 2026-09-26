<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Factory users have agreed to the current Terms and Community Guidelines, so contribution
     * tests run through the real `terms` middleware instead of switching it off. Use
     * unacceptedTerms() to exercise the gate itself.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->termsAcceptances()->create([
                'terms_version' => config('legal.terms_version'),
                'guidelines_version' => config('legal.guidelines_version'),
                'privacy_version' => config('legal.privacy_version'),
                'context' => 'contribution',
                'accepted_at' => now(),
            ]);
        });
    }

    /**
     * A user who hasn't agreed to the Terms of Use / Community Guidelines yet.
     */
    public function unacceptedTerms(): static
    {
        // Runs after configure()'s callback (callbacks run in registration order).
        return $this->afterCreating(fn (User $user) => $user->termsAcceptances()->delete());
    }

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
            // Mirrors the column defaults: Sanctum::actingAs() keeps the in-memory model, which
            // never sees DB defaults, and the `active` middleware reads status off it.
            'role' => 'user',
            'status' => 'active',
        ];
    }

    /**
     * An anonymous guest account, as created by POST auth/guest — no identity at all.
     */
    public function guest(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => null,
            'email' => null,
            'email_verified_at' => null,
            'password' => null,
            'is_guest' => true,
        ]);
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
}
