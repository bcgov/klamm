<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// BRE routes
require __DIR__ . '/bre_routes.php';
// Form routes
require __DIR__ . '/form_routes.php';
