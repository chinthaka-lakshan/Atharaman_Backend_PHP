<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Guides;
use App\Models\ShopOwner;
use App\Models\HotelOwner;
use App\Models\VehicleOwner;
use App\Models\Vehicle;
use App\Models\Hotel;
use App\Models\Shop;
use App\Models\Location;
use App\Models\Item;
use App\Models\LocationHotelReviews;
use App\Models\OtherReviews;
use App\Http\Controllers\Controller;
use App\Http\Controllers\GuidesController;
use App\Http\Controllers\ShopOwnerController;
use App\Http\Controllers\HotelOwnerController;
use App\Http\Controllers\VehicleOwnerController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\HotelController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\LocationsController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\LocationHotelReviewsController;
use App\Http\Controllers\OtherReviewsController;
use App\Http\Controllers\AuthController;



// Auth routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Example: Admin-only route
Route::middleware(['auth:sanctum', 'role:Admin'])->get('/admin/dashboard', function () {
    return response()->json(['message' => 'Welcome Admin!']);
});

// Example: User and Admin route
Route::middleware(['auth:sanctum', 'role:User,Admin'])->get('/user/profile', [AuthController::class, 'profile']);


Route::middleware(['auth:sanctum', 'role:Admin'])->group(function () {
    Route::apiResource('guides', GuidesController::class);
    Route::get('guides/location/{location}', [GuidesController::class, 'getByLocation']);
    Route::apiResource('shop-owners', ShopOwnerController::class);
    Route::apiResource('hotel-owners', HotelOwnerController::class);
    Route::apiResource('vehicle-owners', VehicleOwnerController::class);
    Route::get('vehicle-owners/location/{location}', [VehicleOwnerController::class, 'getByLocation']);
    Route::apiResource('vehicles', VehicleController::class);
    Route::get('vehicles/location/{location}', [VehicleController::class, 'getByLocation']);
    Route::apiResource('hotels', HotelController::class);
    Route::apiResource('shops',ShopController::class);
    Route::get('shops/location/{location}', [ShopController::class, 'getByLocation']);
    Route::apiResource('locations', LocationsController::class);
    Route::get('locations/province/{province}', [LocationsController::class, 'getByProvince']);
    Route::apiResource('items', ItemController::class);
    Route::apiResource('location-hotel-reviews', LocationHotelReviewsController::class);
    Route::apiResource('other-reviews', OtherReviewsController::class);
});
