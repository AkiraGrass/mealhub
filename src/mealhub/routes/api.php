<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RestaurantController;
use App\Http\Controllers\ReservationController;

Route::prefix('auth')->middleware('http.log')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
    Route::post('/refresh',  [AuthController::class, 'refresh']);
    Route::post('/logout',   [AuthController::class, 'logout'])->middleware('auth.jwt');
    Route::post('/logout-all',[AuthController::class, 'logoutAll'])->middleware('auth.jwt');
});

Route::middleware(['http.log','auth.jwt'])->group(function () {
    // User
    Route::get('/me', [UserController::class, 'me']);
    Route::patch('/me', [UserController::class, 'update']);

    // Restaurants
    Route::get('/restaurants', [RestaurantController::class, 'index']);
    Route::get('/restaurants/{restaurantId}', [RestaurantController::class, 'show']);
    Route::post('/restaurants', [RestaurantController::class, 'create']);
    Route::get('/restaurants/{restaurantId}/reservations', [RestaurantController::class, 'reservations']);
    Route::patch('/restaurants/{restaurantId}', [RestaurantController::class, 'update']);
    Route::post('/restaurants/{restaurantId}/admins', [RestaurantController::class, 'addAdmin']);
    Route::delete('/restaurants/{restaurantId}/admins', [RestaurantController::class, 'removeAdmin']);
    Route::get('/restaurants/{restaurantId}/admins', [RestaurantController::class, 'admins']);
    Route::get('/restaurants/{restaurantId}/availability', [RestaurantController::class, 'availability']);
    Route::get('/restaurants/{restaurantId}/availability/detail', [RestaurantController::class, 'availabilityDetail']);
    Route::patch('/restaurants/{restaurantId}/timeslots', [RestaurantController::class, 'updateTimeslots']);

    // Reservations
    Route::post('/reservations', [ReservationController::class, 'create']);
    Route::post('/reservations/cancel', [ReservationController::class, 'cancel']);
    Route::get('/reservations/my', [ReservationController::class, 'my']);
    Route::patch('/reservations', [ReservationController::class, 'update']);
});

// Anonymous reservation query via code or short token
Route::get('/reservations/code/{code}', [ReservationController::class, 'showByCode'])->middleware('http.log');
Route::get('/reservations/short/{token}', [ReservationController::class, 'showByShort'])->middleware('http.log');
