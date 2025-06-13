<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Mail::fake();
        Event::fake();
    }

    public function loginAsAdmin(): static
    {
        /** @var User $admin */
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        return $this->actingAs($admin);
    }

    public function loginAsUser(): static
    {
        /** @var User $user */
        $user = User::factory()->create();
        return $this->actingAs($user);
    }

    /**
     * Create a user with specific role(s)
     */
    public function createUserWithRole(string|array $roles): User
    {
        /** @var User $user */
        $user = User::factory()->create();

        if (is_array($roles)) {
            foreach ($roles as $role) {
                $user->assignRole($role);
            }
        } else {
            $user->assignRole($roles);
        }

        return $user;
    }

    /**
     * Login as user with specific role(s)
     */
    public function loginAsUserWithRole(string|array $roles): static
    {
        $user = $this->createUserWithRole($roles);
        return $this->actingAs($user);
    }

    /**
     * Create users with different roles for testing
     */
    public function createUsersWithRoles(): array
    {
        return [
            'admin' => $this->createUserWithRole('admin'),
            'fodig' => $this->createUserWithRole('fodig'),
            'user' => $this->createUserWithRole('user'),
            'forms' => $this->createUserWithRole('forms'),
            'bre' => $this->createUserWithRole('bre'),
            'form_developer' => $this->createUserWithRole('form-developer'),
            'multiple_roles' => $this->createUserWithRole(['fodig', 'forms']),
            'no_role' => User::factory()->create()
        ];
    }

    /**
     * Ensure all roles exist for testing
     */
    public function createRoles(): void
    {
        $roles = [
            'admin',
            'fodig',
            'user',
            'forms',
            'bre',
            'form-developer'
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }
    }
}
