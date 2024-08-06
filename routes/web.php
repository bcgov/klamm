<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FormViewController;

// Uses the react deployment to render a form based on UUID
Route::get('/forms/rendered-forms/{uuid}/view', [FormViewController::class, 'show'])->name('forms.rendered_forms.view');

// Explicit login route (otherwise it only lives in the admin panel)
Route::get('/login', function () {
    return redirect(route('filament.admin.auth.login'));
})->name('login');

Route::get('/', function () {
    return redirect(route('filament.home.pages.welcome'));
});
