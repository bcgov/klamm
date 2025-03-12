<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FormViewController;

// Explicit login route (otherwise it only lives in the admin panel)
Route::get('/login', function () {
    return redirect(route('filament.admin.auth.login'));
})->name('login');

Route::get('/', function () {
    return redirect(route('filament.home.pages.welcome'));
});
