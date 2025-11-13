<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FormVersionController;

// Form Version Preview
Route::get('/form-versions/{id}/preview-v2-dev', [FormVersionController::class, 'getFormTemplate'])
    ->middleware(['auth:sanctum', 'ability:admin, form-developer']);

// Form Version Logs
Route::get('/form-versions/{id}/logs', [FormVersionController::class, 'getFormVersionLogs'])
    ->middleware(['auth:sanctum', 'ability:admin, form-developer']);

// Form Version Data
Route::get('/form-versions/{id}/data', [FormVersionController::class, 'getFormData']);
