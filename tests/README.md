# Test Documentation

This document covers the testing setup for the Klamm application using **Pest PHP** with Laravel Sail.

## Running Tests

```bash
# Run all tests
sail artisan test

# Run with coverage
sail artisan test --coverage
```

## Test Structure

```
tests/
├── Unit/                             # Model and business logic tests
│   ├── UserModelTest.php             # User CRUD, roles, permissions
│   ├── UserValidationTest.php        # Validation rules and data integrity
│   └── UserRelationshipsTest.php     # Eloquent relationships
├── Feature/                          # Integration and workflow tests
|   ├── Anonymizer/
|   |   └── AnonymousUpload.php       # Anonymous data upload and processing
│   └── Auth/
│       ├── AuthenticationTest.php    # Login/logout, password reset
│       └── RoleAuthorizationTest.php # Role-based access control
├── TestCase.php                      # Base test class with helpers
└── Pest.php                          # Global test functions
```

## Test Helpers Available

**TestCase Methods:**

```php
$this->createUserWithRole('admin');
$this->loginAsUserWithRole(['fodig', 'forms']);
$this->createRoles();
```

**Global Functions:**

```php
createUserWithRole('admin');
loginAsAdmin();
loginAsUser();
```

**Factory States:**

```php
User::factory()->admin()->create();
User::factory()->fodig()->create();
User::factory()->unverified()->create();
```

## Supported Roles

-   `admin` - Full access
-   `fodig` - FODIG resources (full access)
-   `forms` - Forms resources (full access)
-   `bre` - BRE resources (full access)
-   `user` - Read-only access to all resources
-   `form-developer` - Form development

## How-To Guide

### Run Individual Tests

```bash
# Run specific test file
sail artisan test tests/Unit/UserModelTest.php

# Run specific test group
sail artisan test --filter="User Creation"

# Run tests for specific functionality
sail artisan test --filter="Role"
```
