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

// Public routes (no auth required)
// Auth routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/chatbot', [ChatbotController::class, 'ask']);
// Password reset routes
Route::post('/forgot-password', [AuthController::class, 'sendResetLink']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::get('/all_guides', [GuidesController::class, 'index']);
Route::get('/guides/{id}', [GuidesController::class, 'show']);

// Authenticated routes (any logged-in user)
Route::middleware(['auth:sanctum'])->group(function () {
    // User profile - accessible to any authenticated user
    Route::get('/user/profile', [AuthController::class, 'profile']);
    // Get user's role requests
    Route::get('/user/role-requests', function (Request $request) {
        return $request->user()->roleRequests()->with('role')->get();
    });
    // Role requests - accessible to any authenticated user
    Route::post('/role-request', [RoleRequestController::class, 'store']);
    // Logout - accessible to any authenticated user
    Route::post('/logout', [AuthController::class, 'logout']);
    // Locations read access for all authenticated users
    Route::get('/locations', [LocationsController::class, 'index']);
    Route::get('/locations/{id}', [LocationsController::class, 'show']);
    Route::get('locations/province/{province}', [LocationsController::class, 'getByProvince']);
    // Routes to fetch shops/hotels/vehicles by owner
    Route::get('shop-owners/{ownerId}/shops', [ShopController::class, 'getByOwner']);
    Route::get('hotel-owners/{ownerId}/hotels', [HotelController::class, 'getByOwner']);
    Route::get('vehicle-owners/{ownerId}/vehicles', [VehicleController::class, 'getByOwner']);
});

// Admin-only routes
Route::middleware(['auth:sanctum', 'role:Admin'])->get('/admin/dashboard', function () {
    return response()->json(['message' => 'Welcome Admin!']);
});

Route::middleware(['auth:sanctum', 'role:Admin'])->group(function () {
    Route::post('/locations', [LocationsController::class, 'store']);
    Route::put('/locations/{id}', [LocationsController::class, 'update']);
    Route::delete('/locations/{id}', [LocationsController::class, 'destroy']);

    Route::apiResource('guides', GuidesController::class);
    Route::get('guides/location/{location}', [GuidesController::class, 'getByLocation']);

    Route::apiResource('shop-owners', ShopOwnerController::class);

    Route::apiResource('hotel-owners', HotelOwnerController::class);

    Route::apiResource('vehicle-owners', VehicleOwnerController::class);
    Route::get('vehicle-owners/location/{location}', [VehicleOwnerController::class, 'getByLocation']);

    Route::apiResource('shops', ShopController::class);
    Route::get('shops/location/{location}', [ShopController::class, 'getByLocation']);

    Route::apiResource('hotels', HotelController::class);
    Route::get('hotels/location/{location}', [HotelController::class, 'getByLocation']);

    Route::apiResource('vehicles', VehicleController::class);
    Route::get('vehicles/location/{location}', [VehicleController::class, 'getByLocation']);

    Route::apiResource('items', ItemController::class);
    Route::apiResource('location-hotel-reviews', LocationHotelReviewsController::class);
    Route::apiResource('other-reviews', OtherReviewsController::class);
    // Get all users
    Route::get('/users', [AuthController::class, 'getUsers']);
    // Admin Registration & Management
    Route::post('/admin/users', [AuthController::class, 'registerAdmin']);
    Route::put('/admin/users/{id}', [AuthController::class, 'updateUser']);
    Route::delete('/admin/users/{id}', [AuthController::class, 'deleteUser']);

    // Role management routes - Admin only
    Route::prefix('admin')->group(function () {
        Route::get('/role-requests', [AdminRoleRequestController::class, 'index']);
        Route::post('/role-requests/{id}/approve', [AdminRoleRequestController::class, 'approve']);
        Route::post('/role-requests/{id}/reject', [AdminRoleRequestController::class, 'reject']);
    });
});