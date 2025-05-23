<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->assignRole('admin');
        });
    }

    public function user(): static
    {
        return $this->state([]);
    }

    public function fodig(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->assignRole('fodig');
        });
    }

    public function fodigViewOnly(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->assignRole('fodig-view-only');
        });
    }

    public function forms(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->assignRole('forms');
        });
    }

    public function formsViewOnly(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->assignRole('forms-view-only');
        });
    }

    public function bre(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->assignRole('bre');
        });
    }

    public function breViewOnly(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->assignRole('bre-view-only');
        });
    }

    public function formDeveloper(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->assignRole('form-developer');
        });
    }

    public function withRoles(array $roles): static
    {
        return $this->afterCreating(function (User $user) use ($roles) {
            foreach ($roles as $role) {
                $user->assignRole($role);
            }
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
