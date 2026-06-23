<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\RestaurantController;
use App\Http\Controllers\Api\RiderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Every route in this file is automatically prefixed with "/api".
| So Route::post('auth/login', ...) becomes  POST /api/auth/login
*/

Route::prefix('auth')->group(function () {

    // ---- Public routes (no token needed) ----
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'login']);

    // ---- Protected routes ----
    // The client must send the header:  Authorization: Bearer <access_token>
    Route::middleware('auth:api')->group(function () {
        Route::get('me',             [AuthController::class, 'me']);
        Route::post('logout',        [AuthController::class, 'logout']);
        Route::post('refresh-token', [AuthController::class, 'refresh']);
    });
});


/*
|--------------------------------------------------------------------------
| Phase 2: Restaurants, Menu & Orders
|--------------------------------------------------------------------------
*/

// ---- Public browsing (no token needed) ----
// NOTE: "nearby" is declared BEFORE "restaurants/{restaurant}" so the word
// "nearby" is not mistaken for a restaurant id.
Route::get('categories',                  [CategoryController::class, 'index']);
Route::get('restaurants/nearby',          [RestaurantController::class, 'nearby']);
Route::get('restaurants',                 [RestaurantController::class, 'index']);
Route::get('restaurants/{restaurant}',    [RestaurantController::class, 'show']);
Route::get('restaurants/{restaurant}/menu', [MenuController::class, 'index']);
Route::get('menu-items/{menuItem}',       [MenuController::class, 'show']);

// ---- Orders (customer must be logged in) ----
Route::middleware('auth:api')->group(function () {
    Route::post('orders',                 [OrderController::class, 'store']);
    Route::get('orders',                  [OrderController::class, 'index']);
    Route::get('orders/{order}',          [OrderController::class, 'show']);
    Route::post('orders/{order}/cancel',  [OrderController::class, 'cancel']);
});


/*
|--------------------------------------------------------------------------
| Phase 3: Rider endpoints
|--------------------------------------------------------------------------
| All of these require a logged-in user WITH the "rider" role.
| (role:rider,api  -> the ",api" part tells spatie to use our JWT guard)
*/
Route::prefix('rider')->middleware(['auth:api', 'role:rider,api'])->group(function () {
    // static paths first so they aren't mistaken for "{order}"
    Route::get('orders/available', [RiderController::class, 'available']);
    Route::get('orders/history',   [RiderController::class, 'history']);

    Route::post('orders/{order}/accept',    [RiderController::class, 'accept']);
    Route::post('orders/{order}/picked-up', [RiderController::class, 'pickedUp']);
    Route::post('orders/{order}/delivered', [RiderController::class, 'delivered']);

    // The rider app posts the rider's GPS location here every few seconds.
    Route::post('location', [RiderController::class, 'updateLocation']);
});
