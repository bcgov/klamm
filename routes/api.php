<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// BRE routes
require __DIR__ . '/bre_routes.php';

// System Message routes
require __DIR__ . '/system_message_routes.php';
