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
use App\Models\TouristSpot;
use App\Http\Controllers\ChatbotController;
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
use App\Http\Controllers\RoleRequestController;
use App\Http\Controllers\AdminRoleRequestController;

// Public (no auth required) routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/chatbot', [ChatbotController::class, 'ask']);
// Password reset routes
Route::post('/forgot-password', [AuthController::class, 'sendResetLink']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Other public APIs
Route::get('/locations', [LocationsController::class, 'index']);
Route::get('/locations/{id}', [LocationsController::class, 'show']);
Route::get('locations/province/{province}', [LocationsController::class, 'getByProvince']);
Route::get('/all_guides', [GuidesController::class, 'index']);
Route::get('/guides/{id}', [GuidesController::class, 'show']);
Route::get('guides/location/{location}', [GuidesController::class, 'getByLocation']);
Route::get('shops/location/{location}', [ShopController::class, 'getByLocation']);
Route::get('/all_hotels', [HotelController::class, 'index']);
Route::get('/hotels/{id}', [HotelController::class, 'show']);
Route::get('hotels/location/{location}', [HotelController::class, 'getByLocation']);
Route::get('/hotelOwners/{id}', [HotelOwnerController::class, 'show']);
Route::get('vehicle-owners/location/{location}', [VehicleOwnerController::class, 'getByLocation']);
Route::get('vehicles/location/{location}', [VehicleController::class, 'getByLocation']);

// Authenticated routes (any logged-in user)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user/profile', [AuthController::class, 'profile']);
    Route::post('/logout', [AuthController::class, 'logout']);


    // Get user's role requests
    Route::get('/user/role-requests', function (Request $request) {
        return $request->user()->roleRequests()->with('role')->get();
    });
    // Role requests - accessible to any authenticated user
    Route::post('/role-request', [RoleRequestController::class, 'store']);

    // Routes for shop owner of authenticated user
    Route::get('/my-shop-owner', [ShopOwnerController::class, 'getByAuthenticatedUser']);
    Route::put('/my-shop-owner', [ShopOwnerController::class, 'updateByAuthenticatedUser']);
    Route::delete('/my-shop-owner', [ShopOwnerController::class, 'deleteByAuthenticatedUser']);
    // Routes for shops of authenticated user
    Route::get('/my-shops', [ShopController::class, 'getByAuthenticatedOwner']);
    Route::post('/my-shops', [ShopController::class, 'storeByAuthenticatedOwner']);
    Route::put('/my-shops/{id}', [ShopController::class, 'updateByAuthenticatedOwner']);
    Route::delete('/my-shops/{id}', [ShopController::class, 'deleteByAuthenticatedOwner']);

    Route::get('hotel-owners/{ownerId}/hotels', [HotelController::class, 'getByOwner']);
    Route::get('vehicle-owners/{ownerId}/vehicles', [VehicleController::class, 'getByOwner']);
});

// Admin-only routes
Route::middleware(['auth:sanctum', 'role:Admin'])->get('/admin/dashboard', function () {
    return response()->json(['message' => 'Welcome Admin!']);
});

Route::middleware(['auth:sanctum', 'role:Admin'])->group(function () {
    Route::apiResource('locations', LocationsController::class);

    Route::apiResource('guides', GuidesController::class);

    Route::apiResource('shop-owners', ShopOwnerController::class);
    Route::apiResource('shops', ShopController::class);

    Route::apiResource('hotel-owners', HotelOwnerController::class);
    Route::apiResource('hotels', HotelController::class);

    Route::apiResource('vehicle-owners', VehicleOwnerController::class);
    Route::apiResource('vehicles', VehicleController::class);

    Route::get('/users', [AuthController::class, 'getUsers']);
    // Admin Registration & Management
    Route::post('/admin/users', [AuthController::class, 'registerAdmin']);
    Route::put('/admin/users/{id}', [AuthController::class, 'updateUser']);
    Route::delete('/admin/users/{id}', [AuthController::class, 'deleteUser']);

    Route::apiResource('items', ItemController::class);
    Route::apiResource('location-hotel-reviews', LocationHotelReviewsController::class);
    Route::apiResource('other-reviews', OtherReviewsController::class);

    // Role management routes - Admin only
    Route::prefix('admin')->group(function () {
        Route::get('/role-requests', [AdminRoleRequestController::class, 'index']);
        Route::post('/role-requests/{id}/approve', [AdminRoleRequestController::class, 'approve']);
        Route::post('/role-requests/{id}/reject', [AdminRoleRequestController::class, 'reject']);
    });
});