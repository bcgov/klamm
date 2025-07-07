<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
    $this->createRoles();
});

describe('User Validation Rules', function () {
    test('all required fields are validated', function () {
        $validator = Validator::make([], [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('name'))->toBeTrue()
            ->and($validator->errors()->has('email'))->toBeTrue()
            ->and($validator->errors()->has('password'))->toBeTrue();
    });

    test('valid data passes validation', function () {
        $validator = Validator::make([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
        ], [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        expect($validator->fails())->toBeFalse();
    });

    test('email must be unique', function () {
        User::factory()->create(['email' => 'existing@example.com']);

        $validator = Validator::make([
            'name' => 'John Doe',
            'email' => 'existing@example.com',
            'password' => 'password123',
        ], [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('email'))->toBeTrue();
    });

    test('email must be valid format', function () {
        $invalidEmails = [
            'invalid-email',
            '@example.com',
            'user@',
            'user space@example.com',
        ];

        foreach ($invalidEmails as $email) {
            $validator = Validator::make([
                'name' => 'John Doe',
                'email' => $email,
                'password' => 'password123',
            ], [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8',
            ]);

            expect($validator->fails())->toBeTrue()
                ->and($validator->errors()->has('email'))->toBeTrue();
        }
    });

    test('password must meet minimum length', function () {
        $validator = Validator::make([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'short',
        ], [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('password'))->toBeTrue();
    });

    test('name cannot exceed maximum length', function () {
        $longName = str_repeat('a', 256);

        $validator = Validator::make([
            'name' => $longName,
            'email' => 'john@example.com',
            'password' => 'password123',
        ], [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('name'))->toBeTrue();
    });

    test('email cannot exceed maximum length', function () {
        $longEmail = str_repeat('a', 244) . '@example.com';

        $validator = Validator::make([
            'name' => 'John Doe',
            'email' => $longEmail,
            'password' => 'password123',
        ], [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        expect($validator->fails())->toBeTrue()
            ->and($validator->errors()->has('email'))->toBeTrue();
    });
});

describe('User Data Integrity', function () {
    test('password is automatically hashed', function () {
        $plainPassword = 'plaintext123';
        $user = User::factory()->create(['password' => $plainPassword]);

        expect($user->password)->not->toBe($plainPassword)
            ->and(Hash::check($plainPassword, $user->password))->toBeTrue();
    });

    test('email is stored in lowercase', function () {
        $user = User::factory()->create(['email' => 'TEST@EXAMPLE.COM']);

        expect($user->email)->toBe('test@example.com');
    });

    test('timestamps are set correctly', function () {
        $user = User::factory()->create();

        expect($user->created_at)->not->toBeNull()
            ->and($user->updated_at)->not->toBeNull()
            ->and($user->created_at)->toEqual($user->updated_at);
    });

    test('email verification timestamp can be null', function () {
        $user = User::factory()->unverified()->create();

        expect($user->email_verified_at)->toBeNull();
    });

    test('remember token is set', function () {
        $user = User::factory()->create();

        expect($user->remember_token)->not->toBeNull()
            ->and(strlen($user->remember_token))->toBe(10);
    });
});

describe('User Attributes', function () {
    test('fillable attributes are correctly defined', function () {
        $user = new User();
        $fillable = $user->getFillable();

        expect($fillable)->toContain('name')
            ->and($fillable)->toContain('email')
            ->and($fillable)->toContain('password');
    });

    test('hidden attributes are correctly defined', function () {
        $user = User::factory()->create();
        $hidden = $user->getHidden();

        expect($hidden)->toContain('password')
            ->and($hidden)->toContain('remember_token')
            ->and($hidden)->toContain('api_token');
    });

    test('password is hidden in array conversion', function () {
        $user = User::factory()->create(['password' => 'secret123']);
        $userArray = $user->toArray();

        expect($userArray)->not->toHaveKey('password')
            ->and($userArray)->not->toHaveKey('remember_token');
    });

    test('casts are correctly defined', function () {
        $user = new User();
        $casts = $user->getCasts();

        expect($casts)->toHaveKey('email_verified_at')
            ->and($casts['email_verified_at'])->toBe('datetime')
            ->and($casts)->toHaveKey('password')
            ->and($casts['password'])->toBe('hashed');
    });
});

describe('User Edge Cases', function () {
    test('user can be created with special characters in name', function () {
        $specialNames = [
            'José María',
            "O'Connor",
            'Jean-Claude',
            '李小明',
            'محمد',
        ];

        foreach ($specialNames as $name) {
            $user = User::factory()->create(['name' => $name]);
            expect($user->name)->toBe($name);
        }
    });

    test('user can be created with various valid email formats', function () {
        $validEmails = [
            'simple@example.com',
            'user.name@example.com',
            'user+tag@example.com',
            'user_name@example.com',
            'user123@example.com',
            'test@subdomain.example.com',
        ];

        foreach ($validEmails as $email) {
            $user = User::factory()->create(['email' => $email]);
            expect($user->email)->toBe($email);
        }
    });

    test('user creation handles whitespace in name and email', function () {
        $user = User::factory()->create([
            'name' => '  John Doe  ',
            'email' => '  test@example.com  '
        ]);

        expect($user->name)->toBe('  John Doe  ')
            ->and($user->email)->toBe('  test@example.com  ');
    });

    test('user deletion cascades properly', function () {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $userId = $user->id;
        $user->delete();

        expect(User::find($userId))->toBeNull();
    });

    test('user can have empty optional fields', function () {
        $user = User::factory()->create();

        expect($user->api_token)->toBeNull();
    });
});
