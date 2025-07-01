<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FormViewController;
use Illuminate\Support\Facades\Storage;

// Explicit login route (otherwise it only lives in the admin panel)
Route::get('/login', function () {
    return redirect(route('filament.admin.auth.login'));
})->name('login');

Route::get('/', function () {
    return redirect(route('filament.home.pages.welcome'));
});

Route::get('/download/form-json/{filename}', function ($filename) {
    // Validate filename to prevent directory traversal
    if (!preg_match('/^form_[a-zA-Z0-9_\-]+\.json$/', $filename)) {
        abort(404);
    }

    if (!Storage::disk('templates')->exists($filename)) {
        abort(404);
    }

    $fileContents = Storage::disk('templates')->get($filename);

    return response($fileContents)
        ->header('Content-Type', 'application/json')
        ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
})->name('download.form-json');
