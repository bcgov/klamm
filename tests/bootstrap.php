<?php

/*
|--------------------------------------------------------------------------
| Test Bootstrap
|--------------------------------------------------------------------------
|
| This bootstrap file runs before tests execute. It forces the test database
| to be used locally, protecting development data from RefreshDatabase.
| In CI (GitHub Actions), the CI environment variable is set, so this
| override is skipped and the workflow's DB_DATABASE takes effect.
|
*/

require __DIR__ . '/../vendor/autoload.php';

// Only override locally - GitHub Actions sets CI=true
if (! getenv('CI')) {
    // Store original value for cleanup
    $originalDbDatabase = getenv('DB_DATABASE') ?: 'laravel';

    putenv('DB_DATABASE=klamm_testing');
    $_ENV['DB_DATABASE'] = 'klamm_testing';
    $_SERVER['DB_DATABASE'] = 'klamm_testing';

    // Register cleanup to restore original env when tests finish
    register_shutdown_function(function () use ($originalDbDatabase) {
        putenv("DB_DATABASE={$originalDbDatabase}");
        $_ENV['DB_DATABASE'] = $originalDbDatabase;
        $_SERVER['DB_DATABASE'] = $originalDbDatabase;
    });
}
