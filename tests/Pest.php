<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

use App\Models\User;

uses(
    Tests\TestCase::class,
    Illuminate\Foundation\Testing\RefreshDatabase::class,
)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function loginAsAdmin()
{
    $user = User::factory()->create();
    $user->assignRole('admin');
    return test()->actingAs($user);
}

function loginAsUser()
{
    return test()->actingAs(
        User::factory()->create()
    );
}

function createUserWithRole(string|array $roles): User
{
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

function loginAsUserWithRole(string|array $roles)
{
    $user = createUserWithRole($roles);
    return test()->actingAs($user);
}
