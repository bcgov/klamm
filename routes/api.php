<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\FormElementController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['auth:sanctum'])->group(function () {
    // Form Elements API routes
    Route::get('/form-versions/{formVersion}/elements', [FormElementController::class, 'getFormVersionElements']);
    Route::post('/form-elements', [FormElementController::class, 'store']);
    Route::put('/form-elements/{formElement}', [FormElementController::class, 'update']);
    Route::delete('/form-elements/{formElement}', [FormElementController::class, 'destroy']);
    Route::post('/form-elements/reorder', [FormElementController::class, 'reorder']);
});

// BRE routes
require __DIR__ . '/bre_routes.php';
// Form routes
require __DIR__ . '/form_routes.php';
