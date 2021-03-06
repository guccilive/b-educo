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
Route::GET('/tags', \App\Http\Controllers\API\TagController::class);


// Offices
Route::GET('/offices', [\App\Http\Controllers\API\OfficeController::class, 'index']);
Route::GET('/offices/{office}', [\App\Http\Controllers\API\OfficeController::class, 'show']);
Route::POST('/offices', [\App\Http\Controllers\API\OfficeController::class, 'create'])->middleware(['auth:sanctum', 'verified']);
Route::PUT('/offices/{office}', [\App\Http\Controllers\API\OfficeController::class, 'update'])->middleware(['auth:sanctum', 'verified']);
Route::DELETE('/offices/{office}', [\App\Http\Controllers\API\OfficeController::class, 'delete'])->middleware(['auth:sanctum', 'verified']);

// Office Images
Route::POST('/offices/{office}/images', [\App\Http\Controllers\API\OfficeImageController::class, 'store'])->middleware(['auth:sanctum', 'verified']);
Route::DELETE('/offices/{office}/images/{image:id}', [\App\Http\Controllers\API\OfficeImageController::class, 'delete'])->middleware(['auth:sanctum', 'verified']);

// User Reservations
Route::GET('/reservations', [\App\Http\Controllers\API\UserReservationController::class,'index'])->middleware(['auth:sanctum', 'verified']);
Route::POST('/reservations', [\App\Http\Controllers\API\UserReservationController::class,'create'])->name('api.reservation.create')->middleware(['auth:sanctum', 'verified']);
Route::delete('/reservations/cancel/{reservation}', [\App\Http\Controllers\API\UserReservationController::class,'cancel'])->name('api.reservation.cancel')->middleware(['auth:sanctum', 'verified']);

// Host Reservations
Route::GET('/host/reservations', [\App\Http\Controllers\API\HostReservationController::class,'index'])->middleware(['auth:sanctum', 'verified']);
