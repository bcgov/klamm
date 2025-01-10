<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SystemMessageController;

Route::get('/system-messages/last-updated', [SystemMessageController::class, 'getLastUpdated'])
    ->middleware(['auth:sanctum', 'ability:admin,fodig']);

Route::get('/system-messages', [SystemMessageController::class, 'index'])
    ->middleware(['auth:sanctum', 'ability:admin,fodig']);

Route::get('/system-messages/{id}', [SystemMessageController::class, 'show'])
    ->middleware(['auth:sanctum', 'ability:admin,fodig']);
