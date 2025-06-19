<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FormVersionController;
use App\Http\Controllers\FormCommentController;

// Form Version Preview
Route::get('/form-versions/{id}/preview', [FormVersionController::class, 'getFormTemplate'])
    ->middleware(['auth:sanctum', 'ability:admin, form-developer']);

// Form Version Logs
Route::get('/form-versions/{id}/logs', [FormVersionController::class, 'getFormVersionLogs'])
    ->middleware(['auth:sanctum', 'ability:admin, form-developer']);

// Form Version Data
Route::get('/form-versions/{id}/data', [FormVersionController::class, 'getFormData'])
    ->middleware(['auth:sanctum', 'ability:admin, form-developer, user']);

// Form Comments
Route::post('/form-comments', [FormCommentController::class, 'store'])
    ->middleware(['auth:sanctum', 'ability:admin, form-developer, user']);
Route::patch('/form-comments/{id}', [FormCommentController::class, 'update'])
    ->middleware(['auth:sanctum', 'ability:admin, form-developer, user']);
