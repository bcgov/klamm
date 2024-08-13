<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BREFieldController;
use App\Http\Controllers\BREDataTypeController;
use App\Http\Controllers\BREFieldGroupController;
use App\Http\Controllers\BRERuleController;
use App\Http\Controllers\ICMCDWFieldController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Bre Fields

Route::get('/brefields', [BREFieldController::class, 'index']);
Route::get('/brefields/{id}', [BREFieldController::class, 'show']);
Route::post('/brefields', [BREFieldController::class, 'store']);
Route::put('/brefields/{id}', [BREFieldController::class, 'update']);
Route::delete('/brefields/{id}', [BREFieldController::class, 'destroy']);

// Bre Field Groups

Route::get('/brefieldgroups', [BREFieldGroupController::class, 'index']);
Route::get('/brefieldgroups/{id}', [BREFieldGroupController::class, 'show']);
Route::post('/brefieldgroups', [BREFieldGroupController::class, 'store']);
Route::put('/brefieldgroups/{id}', [BREFieldGroupController::class, 'update']);
Route::delete('/brefieldgroups/{id}', [BREFieldGroupController::class, 'destroy']);

// Bre Data Types

Route::get('/bredatatypes', [BREDataTypeController::class, 'index']);
Route::get('/bredatatypes/{id}', [BREDataTypeController::class, 'show']);
Route::post('/bredatatypes', [BREDataTypeController::class, 'store']);
Route::put('/bredatatypes/{id}', [BREDataTypeController::class, 'update']);
Route::delete('/bredatatypes/{id}', [BREDataTypeController::class, 'destroy']);


// Bre Rules

Route::get('/brerules', [BRERuleController::class, 'index']);
Route::get('/brerules/{id}', [BRERuleController::class, 'show']);
Route::post('/brerules', [BRERuleController::class, 'store']);
Route::put('/brerules/{id}', [BRERuleController::class, 'update']);
Route::delete('/brerules/{id}', [BRERuleController::class, 'destroy']);

// ICM CDW Fields

Route::get('/icmcdwfields', [ICMCDWFieldController::class, 'index']);
Route::get('/icmcdwfields/{id}', [ICMCDWFieldController::class, 'show']);
Route::post('/icmcdwfields', [ICMCDWFieldController::class, 'store']);
Route::put('/icmcdwfields/{id}', [ICMCDWFieldController::class, 'update']);
Route::delete('/icmcdwfields/{id}', [ICMCDWFieldController::class, 'destroy']);
