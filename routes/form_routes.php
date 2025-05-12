<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FormVersionController;

// Form Version Preview
Route::get('/form-versions/{id}/preview', [FormVersionController::class, 'getFormTemplate'])
    ->middleware(['auth:sanctum', 'ability:admin, form-developer']);
