<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BREFieldController;
use App\Http\Controllers\BREDataTypeController;
use App\Http\Controllers\BREValueTypeController;
use App\Http\Controllers\BREFieldGroupController;
use App\Http\Controllers\BRERuleController;
use App\Http\Controllers\ICMCDWFieldController;
use App\Http\Controllers\BREDataValidationController;
use App\Http\Controllers\BREValidationTypeController;

// Bre Fields

Route::get('/brefields', [BREFieldController::class, 'index'])->middleware(['auth:sanctum', 'ability:admin,bre']);
Route::get('/brefields/{id}', [BREFieldController::class, 'show'])->middleware(['auth:sanctum', 'ability:admin,bre']);
Route::post('/brefields', [BREFieldController::class, 'store'])->middleware(['auth:sanctum', 'ability:admin,bre']);
Route::put('/brefields/{id}', [BREFieldController::class, 'update'])->middleware(['auth:sanctum', 'ability:admin,bre']);
Route::delete('/brefields/{id}', [BREFieldController::class, 'destroy'])->middleware(['auth:sanctum', 'ability:admin,bre']);

// Bre Field Groups

Route::get('/brefieldgroups', [BREFieldGroupController::class, 'index'])->middleware(['auth:sanctum', 'ability:admin,bre']);
Route::get('/brefieldgroups/{id}', [BREFieldGroupController::class, 'show'])->middleware(['auth:sanctum', 'ability:admin,bre']);
Route::post('/brefieldgroups', [BREFieldGroupController::class, 'store'])->middleware(['auth:sanctum', 'ability:admin,bre']);
Route::put('/brefieldgroups/{id}', [BREFieldGroupController::class, 'update'])->middleware(['auth:sanctum', 'ability:admin,bre']);
Route::delete('/brefieldgroups/{id}', [BREFieldGroupController::class, 'destroy'])->middleware(['auth:sanctum', 'ability:admin,bre']);

// Bre Data Types

Route::get('/bredatatypes', [BREDataTypeController::class, 'index'])->middleware(['auth:sanctum', 'ability:admin,bre']);
Route::get('/bredatatypes/{id}', [BREDataTypeController::class, 'show'])->middleware(['auth:sanctum', 'ability:admin,bre']);
Route::post('/bredatatypes', [BREDataTypeController::class, 'store'])->middleware(['auth:sanctum', 'ability:admin,bre']);
Route::put('/bredatatypes/{id}', [BREDataTypeController::class, 'update'])->middleware(['auth:sanctum', 'ability:admin,bre']);
Route::delete('/bredatatypes/{id}', [BREDataTypeController::class, 'destroy'])->middleware(['auth:sanctum', 'ability:admin,bre']);

// Bre Value Types

Route::get('/brevaluetypes', [BREValueTypeController::class, 'index'])->middleware(['auth:sanctum', 'ability:admin,bre']);
Route::get('/brevaluetypes/{id}', [BREValueTypeController::class, 'show'])->middleware(['auth:sanctum', 'ability:admin,bre']);
Route::post('/brevaluetypes', [BREValueTypeController::class, 'store'])->middleware(['auth:sanctum', 'ability:admin,bre']);
Route::put('/brevaluetypes/{id}', [BREValueTypeController::class, 'update'])->middleware(['auth:sanctum', 'ability:admin,bre']);
Route::delete('/brevaluetypes/{id}', [BREValueTypeController::class, 'destroy'])->middleware(['auth:sanctum', 'ability:admin,bre']);

// Bre Rules

Route::get('/brerules', [BRERuleController::class, 'index'])->middleware(['auth:sanctum', 'ability:admin,bre']);
Route::get('/brerules/{id}', [BRERuleController::class, 'show'])->middleware(['auth:sanctum', 'ability:admin,bre']);
Route::post('/brerules', [BRERuleController::class, 'store'])->middleware(['auth:sanctum', 'ability:admin,bre']);
Route::put('/brerules/{id}', [BRERuleController::class, 'update'])->middleware(['auth:sanctum', 'ability:admin,bre']);
Route::delete('/brerules/{id}', [BRERuleController::class, 'destroy'])->middleware(['auth:sanctum', 'ability:admin,bre']);

// ICM CDW Fields

Route::get('/icmcdwfields', [ICMCDWFieldController::class, 'index'])->middleware(['auth:sanctum', 'ability:admin,bre']);
Route::get('/icmcdwfields/{id}', [ICMCDWFieldController::class, 'show'])->middleware(['auth:sanctum', 'ability:admin,bre']);
Route::post('/icmcdwfields', [ICMCDWFieldController::class, 'store'])->middleware(['auth:sanctum', 'ability:admin,bre']);
Route::put('/icmcdwfields/{id}', [ICMCDWFieldController::class, 'update'])->middleware(['auth:sanctum', 'ability:admin,bre']);
Route::delete('/icmcdwfields/{id}', [ICMCDWFieldController::class, 'destroy'])->middleware(['auth:sanctum', 'ability:admin,bre']);

// BRE Data Validations

Route::get('/bredatavalidations', [BREDataValidationController::class, 'index'])->middleware(['auth:sanctum', 'ability:admin,bre']);
Route::get('/bredatavalidations/{id}', [BREDataValidationController::class, 'show'])->middleware(['auth:sanctum', 'ability:admin,bre']);
Route::post('/bredatavalidations', [BREDataValidationController::class, 'store'])->middleware(['auth:sanctum', 'ability:admin,bre']);
Route::put('/bredatavalidations/{id}', [BREDataValidationController::class, 'update'])->middleware(['auth:sanctum', 'ability:admin,bre']);
Route::delete('/bredatavalidations/{id}', [BREDataValidationController::class, 'destroy'])->middleware(['auth:sanctum', 'ability:admin,bre']);

// BRE Validation Types

Route::get('/brevalidationtypes', [BREValidationTypeController::class, 'index'])->middleware(['auth:sanctum', 'ability:admin,bre']);
Route::get('/brevalidationtypes/{id}', [BREValidationTypeController::class, 'show'])->middleware(['auth:sanctum', 'ability:admin,bre']);
Route::post('/brevalidationtypes', [BREValidationTypeController::class, 'store'])->middleware(['auth:sanctum', 'ability:admin,bre']);
Route::put('/brevalidationtypes/{id}', [BREValidationTypeController::class, 'update'])->middleware(['auth:sanctum', 'ability:admin,bre']);
Route::delete('/brevalidationtypes/{id}', [BREValidationTypeController::class, 'destroy'])->middleware(['auth:sanctum', 'ability:admin,bre']);
