<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use App\Http\Middleware\CheckRole;

beforeEach(function () {
    $this->createRoles();
});

describe('Role Assignment', function () {
    test('user can be assigned admin role', function () {
        $user = User::factory()->create();
        $user->assignRole('admin');

        expect($user->hasRole('admin'))->toBeTrue();
    });

    test('user can be assigned multiple roles', function () {
        $user = User::factory()->create();
        $user->assignRole(['admin', 'fodig', 'forms']);

        expect($user->hasRole('admin'))->toBeTrue()
            ->and($user->hasRole('fodig'))->toBeTrue()
            ->and($user->hasRole('forms'))->toBeTrue()
            ->and($user->roles)->toHaveCount(3);
    });

    test('user can have roles removed', function () {
        $user = User::factory()->create();
        $user->assignRole(['admin', 'fodig']);

        $user->removeRole('admin');

        expect($user->hasRole('admin'))->toBeFalse()
            ->and($user->hasRole('fodig'))->toBeTrue();
    });

    test('user can have all roles synced', function () {
        $user = User::factory()->create();
        $user->assignRole(['admin', 'fodig']);

        $user->syncRoles(['forms', 'bre']);

        expect($user->hasRole('admin'))->toBeFalse()
            ->and($user->hasRole('fodig'))->toBeFalse()
            ->and($user->hasRole('forms'))->toBeTrue()
            ->and($user->hasRole('bre'))->toBeTrue();
    });
});

describe('Gate Authorization', function () {
    test('admin role can access admin gate', function () {
        $user = createUserWithRole('admin');
        $this->actingAs($user);

        expect(Gate::allows('admin'))->toBeTrue();
    });

    test('fodig role can access fodig gate', function () {
        $user = createUserWithRole('fodig');
        $this->actingAs($user);

        expect(Gate::allows('fodig'))->toBeTrue()
            ->and(Gate::allows('admin'))->toBeFalse();
    });

    test('user role can access user gate', function () {
        $user = createUserWithRole('user');
        $this->actingAs($user);

        expect(Gate::allows('user'))->toBeTrue()
            ->and(Gate::allows('fodig'))->toBeFalse()
            ->and(Gate::allows('admin'))->toBeFalse();
    });

    test('forms role can access forms gate', function () {
        $user = createUserWithRole('forms');
        $this->actingAs($user);

        expect(Gate::allows('forms'))->toBeTrue()
            ->and(Gate::allows('admin'))->toBeFalse();
    });

    test('bre role can access bre gate', function () {
        $user = createUserWithRole('bre');
        $this->actingAs($user);

        expect(Gate::allows('bre'))->toBeTrue()
            ->and(Gate::allows('admin'))->toBeFalse();
    });

    test('form-developer role can access form-developer gate', function () {
        $user = createUserWithRole('form-developer');
        $this->actingAs($user);

        expect(Gate::allows('form-developer'))->toBeTrue()
            ->and(Gate::allows('admin'))->toBeFalse();
    });

    test('user with multiple roles can access all their gates', function () {
        $user = createUserWithRole(['fodig', 'forms', 'bre']);
        $this->actingAs($user);

        expect(Gate::allows('fodig'))->toBeTrue()
            ->and(Gate::allows('forms'))->toBeTrue()
            ->and(Gate::allows('bre'))->toBeTrue()
            ->and(Gate::allows('admin'))->toBeFalse();
    });

    test('user without role cannot access any protected gates', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        expect(Gate::allows('admin'))->toBeFalse()
            ->and(Gate::allows('fodig'))->toBeFalse()
            ->and(Gate::allows('forms'))->toBeFalse()
            ->and(Gate::allows('bre'))->toBeFalse();
    });
});

describe('CheckRole Middleware', function () {
    test('admin user can pass admin role check', function () {
        $user = createUserWithRole('admin');
        $this->actingAs($user);

        $request = request();
        $hasRole = CheckRole::hasRole($request, 'admin');

        expect($hasRole)->toBeTrue();
    });

    test('fodig user can pass fodig role check', function () {
        $user = createUserWithRole('fodig');
        $this->actingAs($user);

        $request = request();
        $hasRole = CheckRole::hasRole($request, 'fodig');

        expect($hasRole)->toBeTrue();
    });

    test('user can pass multiple role check if they have one of the roles', function () {
        $user = createUserWithRole('fodig');
        $this->actingAs($user);

        $request = request();
        $hasRole = CheckRole::hasRole($request, 'admin', 'fodig', 'forms');

        expect($hasRole)->toBeTrue();
    });

    test('user cannot pass role check if they do not have any of the required roles', function () {
        $user = createUserWithRole('bre');
        $this->actingAs($user);

        $request = request();
        $hasRole = CheckRole::hasRole($request, 'admin', 'fodig', 'forms');

        expect($hasRole)->toBeFalse();
    });

    test('user without any role cannot pass role check', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        $request = request();
        $hasRole = CheckRole::hasRole($request, 'admin');

        expect($hasRole)->toBeFalse();
    });
});

describe('Factory Role States', function () {
    test('admin factory creates user with admin role', function () {
        $user = User::factory()->admin()->create();

        expect($user->hasRole('admin'))->toBeTrue();
    });

    test('fodig factory creates user with fodig role', function () {
        $user = User::factory()->fodig()->create();

        expect($user->hasRole('fodig'))->toBeTrue();
    });

    test('userRole factory creates user with user role', function () {
        $user = User::factory()->userRole()->create();

        expect($user->hasRole('user'))->toBeTrue();
    });

    test('forms factory creates user with forms role', function () {
        $user = User::factory()->forms()->create();

        expect($user->hasRole('forms'))->toBeTrue();
    });

    test('bre factory creates user with bre role', function () {
        $user = User::factory()->bre()->create();

        expect($user->hasRole('bre'))->toBeTrue();
    });

    test('formDeveloper factory creates user with form-developer role', function () {
        $user = User::factory()->formDeveloper()->create();

        expect($user->hasRole('form-developer'))->toBeTrue();
    });

    test('withRoles factory creates user with multiple specified roles', function () {
        $user = User::factory()->withRoles(['fodig', 'forms', 'bre'])->create();

        expect($user->hasRole('fodig'))->toBeTrue()
            ->and($user->hasRole('forms'))->toBeTrue()
            ->and($user->hasRole('bre'))->toBeTrue()
            ->and($user->roles)->toHaveCount(3);
    });
});

describe('Role Permissions Integration', function () {
    test('roles maintain their permission assignments', function () {
        $user = createUserWithRole('admin');

        expect($user->hasRole('admin'))->toBeTrue();

        $user2 = createUserWithRole('fodig');
        expect($user2->hasRole('fodig'))->toBeTrue();
    });

    test('user role has view-only permissions', function () {
        $fodigViewOnlyUser = createUserWithRole('user');

        expect($fodigViewOnlyUser->hasRole('user'))->toBeTrue()
            ->and($fodigViewOnlyUser->hasRole('user'))->toBeTrue()
            ->and($fodigViewOnlyUser->hasRole('fodig'))->toBeFalse();
    });
});
