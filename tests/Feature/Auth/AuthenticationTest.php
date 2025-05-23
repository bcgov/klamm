<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    $this->createRoles();
});

describe('User Authentication Basics', function () {
    test('user can be authenticated', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        expect(Auth::check())->toBeTrue()
            ->and(Auth::user()->id)->toBe($user->id);
    });

    test('user can be logged out', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        expect(Auth::check())->toBeTrue();

        Auth::logout();

        expect(Auth::check())->toBeFalse();
    });

    test('password verification works', function () {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        expect(Hash::check('password123', $user->password))->toBeTrue()
            ->and(Hash::check('wrongpassword', $user->password))->toBeFalse();
    });
});

describe('Password Reset Functionality', function () {
    test('password reset token can be created', function () {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        expect($token)->toBeString()
            ->and(strlen($token))->toBeGreaterThan(10);
    });

    test('password can be reset with valid token', function () {
        $user = User::factory()->create(['password' => Hash::make('oldpassword')]);
        $token = Password::createToken($user);

        $status = Password::reset([
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
            'token' => $token
        ], function ($user, $password) {
            $user->forceFill([
                'password' => Hash::make($password)
            ])->save();
        });

        expect($status)->toBe(Password::PASSWORD_RESET)
            ->and(Hash::check('newpassword123', $user->fresh()->password))->toBeTrue();
    });

    test('password reset fails with invalid token', function () {
        $user = User::factory()->create();

        $status = Password::reset([
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
            'token' => 'invalid-token'
        ], function ($user, $password) {
            $user->forceFill([
                'password' => Hash::make($password)
            ])->save();
        });

        expect($status)->toBe(Password::INVALID_TOKEN);
    });
});

describe('Filament Admin Access', function () {
    test('authenticated user can potentially access filament admin panel', function () {
        $user = User::factory()->create();

        // Test the canAccessPanel method directly since route testing 
        // requires actual Filament setup
        $panel = new \Filament\Panel('admin');
        expect($user->canAccessPanel($panel))->toBeTrue();
    });

    test('user with admin role has admin privileges', function () {
        $user = createUserWithRole('admin');
        $this->actingAs($user);

        expect(\Illuminate\Support\Facades\Gate::allows('admin'))->toBeTrue();
    });

    test('guest user should not have admin access', function () {
        expect(\Illuminate\Support\Facades\Gate::allows('admin'))->toBeFalse();
    });
});

describe('User Session Management', function () {
    test('multiple users can be authenticated in different sessions', function () {
        $user1 = User::factory()->create(['name' => 'User One']);
        $user2 = User::factory()->create(['name' => 'User Two']);

        // Test user1
        $this->actingAs($user1);
        expect(Auth::user()->name)->toBe('User One');

        // Test user2
        $this->actingAs($user2);
        expect(Auth::user()->name)->toBe('User Two');
    });

    test('user remember token can be regenerated', function () {
        $user = User::factory()->create();
        $originalToken = $user->remember_token;

        $user->setRememberToken(\Illuminate\Support\Str::random(60));
        $user->save();

        expect($user->remember_token)->not->toBe($originalToken)
            ->and($user->remember_token)->toBeString();
    });
});
