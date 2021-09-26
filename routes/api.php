<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

// Tags
Route::get('/tags', \App\Http\Controllers\API\TagController::class);


// Offices
Route::get('/offices', [\App\Http\Controllers\API\OfficeController::class, 'index']);
Route::get('/offices/{office}', [\App\Http\Controllers\API\OfficeController::class, 'show']);
Route::post('/offices', [\App\Http\Controllers\API\OfficeController::class, 'create'])->middleware(['auth:sanctum', 'verified']);
