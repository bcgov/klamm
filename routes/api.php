<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BREFieldController;
use App\Http\Controllers\BREDataTypeController;
use App\Http\Controllers\BREValueTypeController;
use App\Http\Controllers\BREFieldGroupController;
use App\Http\Controllers\BRERuleController;
use App\Http\Controllers\ICMCDWFieldController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Bre Fields

Route::get('/brefields', [BREFieldController::class, 'index'])->middleware('auth:sanctum');
Route::get('/brefields/{id}', [BREFieldController::class, 'show'])->middleware('auth:sanctum');
Route::post('/brefields', [BREFieldController::class, 'store'])->middleware('auth:sanctum');
Route::put('/brefields/{id}', [BREFieldController::class, 'update'])->middleware('auth:sanctum');
Route::delete('/brefields/{id}', [BREFieldController::class, 'destroy'])->middleware('auth:sanctum');

// Bre Field Groups

Route::get('/brefieldgroups', [BREFieldGroupController::class, 'index'])->middleware('auth:sanctum');
Route::get('/brefieldgroups/{id}', [BREFieldGroupController::class, 'show'])->middleware('auth:sanctum');
Route::post('/brefieldgroups', [BREFieldGroupController::class, 'store'])->middleware('auth:sanctum');
Route::put('/brefieldgroups/{id}', [BREFieldGroupController::class, 'update'])->middleware('auth:sanctum');
Route::delete('/brefieldgroups/{id}', [BREFieldGroupController::class, 'destroy'])->middleware('auth:sanctum');

// Bre Data Types

Route::get('/bredatatypes', [BREDataTypeController::class, 'index'])->middleware('auth:sanctum');
Route::get('/bredatatypes/{id}', [BREDataTypeController::class, 'show'])->middleware('auth:sanctum');
Route::post('/bredatatypes', [BREDataTypeController::class, 'store'])->middleware('auth:sanctum');
Route::put('/bredatatypes/{id}', [BREDataTypeController::class, 'update'])->middleware('auth:sanctum');
Route::delete('/bredatatypes/{id}', [BREDataTypeController::class, 'destroy'])->middleware('auth:sanctum');

// Bre Value Types

Route::get('/brevaluetypes', [BREValueTypeController::class, 'index'])->middleware('auth:sanctum');
Route::get('/brevaluetypes/{id}', [BREValueTypeController::class, 'show'])->middleware('auth:sanctum');
Route::post('/brevaluetypes', [BREValueTypeController::class, 'store'])->middleware('auth:sanctum');
Route::put('/brevaluetypes/{id}', [BREValueTypeController::class, 'update'])->middleware('auth:sanctum');
Route::delete('/brevaluetypes/{id}', [BREValueTypeController::class, 'destroy'])->middleware('auth:sanctum');

// Bre Rules

Route::get('/brerules', [BRERuleController::class, 'index'])->middleware('auth:sanctum');
Route::get('/brerules/{id}', [BRERuleController::class, 'show'])->middleware('auth:sanctum');
Route::post('/brerules', [BRERuleController::class, 'store'])->middleware('auth:sanctum');
Route::put('/brerules/{id}', [BRERuleController::class, 'update'])->middleware('auth:sanctum');
Route::delete('/brerules/{id}', [BRERuleController::class, 'destroy'])->middleware('auth:sanctum');

// ICM CDW Fields

Route::get('/icmcdwfields', [ICMCDWFieldController::class, 'index'])->middleware('auth:sanctum');
Route::get('/icmcdwfields/{id}', [ICMCDWFieldController::class, 'show'])->middleware('auth:sanctum');
Route::post('/icmcdwfields', [ICMCDWFieldController::class, 'store'])->middleware('auth:sanctum');
Route::put('/icmcdwfields/{id}', [ICMCDWFieldController::class, 'update'])->middleware('auth:sanctum');
Route::delete('/icmcdwfields/{id}', [ICMCDWFieldController::class, 'destroy'])->middleware('auth:sanctum');
