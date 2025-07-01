<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->createRoles();
});

// describe('User Creation', function () {
//     test('user can be created', function () {
//         $user = User::factory()->create();

//         expect($user)->toBeInstanceOf(User::class)
//             ->and($user->id)->toBeGreaterThan(0);
//     });

//     test('user can have a name', function () {
//         $user = User::factory()->create(['name' => 'Test User']);

//         expect($user->name)->toBe('Test User');
//     });

//     test('user can have an email', function () {
//         $email = 'test@example.com';
//         $user = User::factory()->create(['email' => $email]);

//         expect($user->email)->toBe($email);
//     });

//     test('user email can be verified', function () {
//         $user = User::factory()->create();
//         expect($user->email_verified_at)->not->toBeNull();

//         $unverifiedUser = User::factory()->unverified()->create();
//         expect($unverifiedUser->email_verified_at)->toBeNull();
//     });

//     test('user password is hashed', function () {
//         $user = User::factory()->create(['password' => 'plaintext']);

//         expect(Hash::check('plaintext', $user->password))->toBeTrue()
//             ->and($user->password)->not->toBe('plaintext');
//     });
// });

// describe('User Validation', function () {
//     test('name is required', function () {
//         expect(fn() => User::create([
//             'email' => 'test@example.com',
//             'password' => 'password'
//         ]))->toThrow(\Illuminate\Database\QueryException::class);
//     });

//     test('email is required', function () {
//         expect(fn() => User::create([
//             'name' => 'Test User',
//             'password' => 'password'
//         ]))->toThrow(\Illuminate\Database\QueryException::class);
//     });

//     test('password is required', function () {
//         expect(fn() => User::create([
//             'name' => 'Test User',
//             'email' => 'test@example.com'
//         ]))->toThrow(\Illuminate\Database\QueryException::class);
//     });

//     test('email must be unique', function () {
//         $email = 'test@example.com';
//         User::factory()->create(['email' => $email]);

//         expect(fn() => User::factory()->create(['email' => $email]))
//             ->toThrow(\Illuminate\Database\QueryException::class);
//     });
// });

// describe('User Deletion', function () {
//     test('user can be deleted', function () {
//         $user = User::factory()->create();
//         $userId = $user->id;

//         $user->delete();

//         expect(User::find($userId))->toBeNull();
//     });

//     test('user with roles can be deleted', function () {
//         $user = User::factory()->create();
//         $user->assignRole('admin');
//         $userId = $user->id;

//         $user->delete();

//         expect(User::find($userId))->toBeNull();
//     });
// });

// describe('User Roles', function () {
//     test('user can exist without roles', function () {
//         $user = User::factory()->create();

//         expect($user->roles)->toHaveCount(0)
//             ->and($user->hasAnyRole())->toBeFalse();
//     });

//     test('user can be assigned a single role', function () {
//         $user = User::factory()->create();
//         $user->assignRole('admin');

//         expect($user->hasRole('admin'))->toBeTrue()
//             ->and($user->roles)->toHaveCount(1);
//     });

//     test('user can be assigned multiple roles', function () {
//         $user = User::factory()->create();
//         $user->assignRole(['admin', 'fodig']);

//         expect($user->hasRole('admin'))->toBeTrue()
//             ->and($user->hasRole('fodig'))->toBeTrue()
//             ->and($user->roles)->toHaveCount(2);
//     });

//     test('user can have role removed', function () {
//         $user = User::factory()->create();
//         $user->assignRole(['admin', 'fodig']);

//         $user->removeRole('admin');

//         expect($user->hasRole('admin'))->toBeFalse()
//             ->and($user->hasRole('fodig'))->toBeTrue()
//             ->and($user->roles)->toHaveCount(1);
//     });

//     test('user can have all roles removed', function () {
//         $user = User::factory()->create();
//         $user->assignRole(['admin', 'fodig', 'forms']);

//         $user->syncRoles([]);

//         expect($user->roles)->toHaveCount(0)
//             ->and($user->hasAnyRole())->toBeFalse();
//     });

//     test('user can sync roles', function () {
//         $user = User::factory()->create();
//         $user->assignRole(['admin', 'fodig']);

//         $user->syncRoles(['forms', 'bre']);

//         expect($user->hasRole('admin'))->toBeFalse()
//             ->and($user->hasRole('fodig'))->toBeFalse()
//             ->and($user->hasRole('forms'))->toBeTrue()
//             ->and($user->hasRole('bre'))->toBeTrue()
//             ->and($user->roles)->toHaveCount(2);
//     });
// });

// describe('User Gates and Permissions', function () {
//     test('admin user can access admin gate', function () {
//         $user = createUserWithRole('admin');
//         $this->actingAs($user);

//         expect(\Illuminate\Support\Facades\Gate::allows('admin'))->toBeTrue();
//     });

//     test('fodig user can access fodig gate', function () {
//         $user = createUserWithRole('fodig');
//         $this->actingAs($user);

//         expect(\Illuminate\Support\Facades\Gate::allows('fodig'))->toBeTrue();
//     });

//     test('user without role cannot access admin gate', function () {
//         $user = User::factory()->create();
//         $this->actingAs($user);

//         expect(\Illuminate\Support\Facades\Gate::allows('admin'))->toBeFalse();
//     });

//     test('user with multiple roles can access all their gates', function () {
//         $user = createUserWithRole(['fodig', 'forms']);
//         $this->actingAs($user);

//         expect(\Illuminate\Support\Facades\Gate::allows('fodig'))->toBeTrue()
//             ->and(\Illuminate\Support\Facades\Gate::allows('forms'))->toBeTrue()
//             ->and(\Illuminate\Support\Facades\Gate::allows('admin'))->toBeFalse();
//     });
// });

// describe('User Factory States', function () {
//     test('admin factory state creates user with admin role', function () {
//         $user = User::factory()->admin()->create();

//         expect($user->hasRole('admin'))->toBeTrue();
//     });

//     test('unverified factory state creates unverified user', function () {
//         $user = User::factory()->unverified()->create();

//         expect($user->email_verified_at)->toBeNull();
//     });
// });

// describe('User Relationships', function () {
//     test('user can have business areas', function () {
//         $user = User::factory()->create();

//         expect($user->businessAreas())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class);
//     });

//     test('user can have activities', function () {
//         $user = User::factory()->create();

//         expect($user->activities())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphMany::class);
//     });
// });

// describe('User Authentication', function () {
//     test('user can access filament panel', function () {
//         $user = User::factory()->create();
//         $panel = new \Filament\Panel('admin');

//         expect($user->canAccessPanel($panel))->toBeTrue();
//     });
// });
