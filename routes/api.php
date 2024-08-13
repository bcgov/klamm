<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BREFieldController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Bre Fields

Route::get('/brefields', [BREFieldController::class, 'index']);
Route::get('/brefields/{id}', [BREFieldController::class, 'show']);
Route::post('/brefields', [BREFieldController::class, 'store']);
Route::put('/brefields/{id}', [BREFieldController::class, 'update']);
Route::delete('/brefields/{id}', [BREFieldController::class, 'destroy']);
