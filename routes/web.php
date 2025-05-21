<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FormViewController;
use App\Http\Controllers\FormTemplateController;

// Explicit login route (otherwise it only lives in the admin panel)
Route::get('/login', function () {
    return redirect(route('filament.admin.auth.login'));
})->name('login');

Route::get('/', function () {
    return redirect(route('filament.home.pages.welcome'));
});

// Form template download route
Route::get('/download-template/{filename}', [FormTemplateController::class, 'download'])
    ->middleware('auth')
    ->name('download.form-template');
