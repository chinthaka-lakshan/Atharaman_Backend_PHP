<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Guide;
use App\Models\ShopOwner;
use App\Models\HotelOwner;
use App\Models\VehicleOwner;
use App\Models\Vehicle;
use App\Models\Hotel;
use App\Models\Shop;
use App\Models\Location;
use App\Models\Item;
use App\Models\Review;
use App\Models\WebsiteReview;
use App\Models\TouristSpot;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\ShopOwnerController;
use App\Http\Controllers\HotelOwnerController;
use App\Http\Controllers\VehicleOwnerController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\HotelController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\LocationsController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\WebsiteReviewController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RoleRequestController;
use App\Http\Controllers\AdminRoleRequestController;

Route::post('/chatbot', [ChatbotController::class, 'ask']);
// Password reset routes
Route::post('/forgot-password', [AuthController::class, 'sendResetLink']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/locations', [LocationsController::class, 'index']);
Route::get('/locations/{id}', [LocationsController::class, 'show']);
Route::get('/locations/province/{province}', [LocationsController::class, 'getByProvince']);
Route::get('/locations/{id}/related-data', [LocationsController::class, 'getRelatedData']);
Route::get('/guides', [GuideController::class, 'index']);
Route::get('/guides/{id}', [GuideController::class, 'show']);
Route::get('/guides/location/{location}', [GuideController::class, 'getByLocation']);
Route::get('/shop-owners', [ShopOwnerController::class, 'index']);
Route::get('/shop-owners/{id}', [ShopOwnerController::class, 'show']);
Route::get('/shop-owners/{ownerId}/shops', [ShopController::class, 'getByOwner']);
Route::get('/shops', [ShopController::class, 'index']);
Route::get('/shops/{id}', [ShopController::class, 'show']);
Route::get('/shops/location/{location}', [ShopController::class, 'getByLocation']);
Route::get('/shops/{shopId}/items', [ItemController::class, 'getByShop']);
Route::get('/hotel-owners', [HotelOwnerController::class, 'index']);
Route::get('/hotel-owners/{id}', [HotelOwnerController::class, 'show']);
Route::get('/hotel-owners/{ownerId}/hotels', [HotelController::class, 'getByOwner']);
Route::get('/hotels', [HotelController::class, 'index']);
Route::get('/hotels/{id}', [HotelController::class, 'show']);
Route::get('/hotels/location/{location}', [HotelController::class, 'getByLocation']);
Route::get('/vehicle-owners', [VehicleOwnerController::class, 'index']);
Route::get('/vehicle-owners/{id}', [VehicleOwnerController::class, 'show']);
Route::get('/vehicle-owners/{ownerId}/vehicles', [VehicleController::class, 'getByOwner']);
Route::get('/vehicles', [VehicleController::class, 'index']);
Route::get('/vehicles/{id}', [VehicleController::class, 'show']);
Route::get('/vehicles/location/{location}', [VehicleController::class, 'getByLocation']);

// Public review routes
Route::get('/reviews/entity/{entityType}/{entityId}', [ReviewController::class, 'getReviewsByEntity']);
Route::get('/website-reviews', [WebsiteReviewController::class, 'index']);
Route::get('/website-reviews/recent/{limit?}', [WebsiteReviewController::class, 'getRecentReviews']);

// Authenticated routes (any logged-in user)
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user/profile', [AuthController::class, 'profile']);
    Route::post('/logout', [AuthController::class, 'logout']);
    // Routes for guide of authenticated user
    Route::get('/my-guide', [GuideController::class, 'getByAuthenticatedUser']);
    Route::put('/my-guide', [GuideController::class, 'updateByAuthenticatedUser']);
    Route::delete('/my-guide', [GuideController::class, 'deleteByAuthenticatedUser']);
    // Routes for shop owner of authenticated user
    Route::get('/my-shop-owner', [ShopOwnerController::class, 'getByAuthenticatedUser']);
    Route::put('/my-shop-owner', [ShopOwnerController::class, 'updateByAuthenticatedUser']);
    Route::delete('/my-shop-owner', [ShopOwnerController::class, 'deleteByAuthenticatedUser']);
    // Routes for shops of authenticated user
    Route::get('/my-shops', [ShopController::class, 'getByAuthenticatedOwner']);
    Route::post('/my-shops', [ShopController::class, 'storeByAuthenticatedOwner']);
    Route::put('/my-shops/{id}', [ShopController::class, 'updateByAuthenticatedOwner']);
    Route::delete('/my-shops/{id}', [ShopController::class, 'deleteByAuthenticatedOwner']);
    // Routes for hotel owner of authenticated user
    Route::get('/my-hotel-owner', [HotelOwnerController::class, 'getByAuthenticatedUser']);
    Route::put('/my-hotel-owner', [HotelOwnerController::class, 'updateByAuthenticatedUser']);
    Route::delete('/my-hotel-owner', [HotelOwnerController::class, 'deleteByAuthenticatedUser']);
    // Routes for hotels of authenticated user
    Route::get('/my-hotels', [HotelController::class, 'getByAuthenticatedOwner']);
    Route::post('/my-hotels', [HotelController::class, 'storeByAuthenticatedOwner']);
    Route::put('/my-hotels/{id}', [HotelController::class, 'updateByAuthenticatedOwner']);
    Route::delete('/my-hotels/{id}', [HotelController::class, 'deleteByAuthenticatedOwner']);
    // Routes for vehicle owner of authenticated user
    Route::get('/my-vehicle-owner', [VehicleOwnerController::class, 'getByAuthenticatedUser']);
    Route::put('/my-vehicle-owner', [VehicleOwnerController::class, 'updateByAuthenticatedUser']);
    Route::delete('/my-vehicle-owner', [VehicleOwnerController::class, 'deleteByAuthenticatedUser']);
    // Routes for vehicles of authenticated user
    Route::post('/check-registration-number-availability', [VehicleController::class, 'checkRegistrationNumber']);
    Route::get('/my-vehicles', [VehicleController::class, 'getByAuthenticatedOwner']);
    Route::post('/my-vehicles', [VehicleController::class, 'storeByAuthenticatedOwner']);
    Route::put('/my-vehicles/{id}', [VehicleController::class, 'updateByAuthenticatedOwner']);
    Route::delete('/my-vehicles/{id}', [VehicleController::class, 'deleteByAuthenticatedOwner']);

    // Review routes
    Route::get('/reviews', [ReviewController::class, 'index']);
    Route::get('/reviews/{id}', [ReviewController::class, 'show']);
    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::put('/reviews/{id}', [ReviewController::class, 'update']);
    Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);
    Route::get('/user/reviews', [ReviewController::class, 'getUserReviews']);
    // Website review routes
    Route::get('/website-reviews/{id}', [WebsiteReviewController::class, 'show']);
    Route::post('/website-reviews', [WebsiteReviewController::class, 'store']);
    Route::post('/website-reviews/{id}', [WebsiteReviewController::class, 'update']);
    Route::delete('/website-reviews/{id}', [WebsiteReviewController::class, 'destroy']);
    Route::get('/user/website-reviews', [WebsiteReviewController::class, 'getUserWebsiteReviews']);

    // Get user's role requests
    Route::get('/user/role-requests', function (Request $request) {
        return $request->user()->roleRequests()->with('role')->get();
    });
    // Role requests - accessible to any authenticated user
    Route::post('/check-nic-availability', [RoleRequestController::class, 'checkNic']);
    Route::post('/role-request', [RoleRequestController::class, 'store']);

    // Role requests - accessible to any authenticated user
    Route::prefix('role-requests')->group(function () {
        Route::get('/', [RoleRequestController::class, 'index']);
        Route::get('/pending', [RoleRequestController::class, 'getPendingRequests']);
        Route::get('/{id}', [RoleRequestController::class, 'show']);
        Route::post('/', [RoleRequestController::class, 'store']);
        Route::delete('/{id}', [RoleRequestController::class, 'cancel']);
        Route::get('/available-roles', [RoleRequestController::class, 'getAvailableRoles']);
    });
    // Routes for items of authenticated user
    Route::get('/my-items', [ItemController::class, 'getByAuthenticatedUser']);
    Route::post('/my-items', [ItemController::class, 'storeByAuthenticatedUser']);
    Route::put('/my-items/{id}', [ItemController::class, 'updateByAuthenticatedUser']);
    Route::delete('/my-items/{id}', [ItemController::class, 'deleteByAuthenticatedUser']);
    Route::get('/my-shops/{shopId}/items', [ItemController::class, 'getByAuthenticatedShop']);
});

// Admin-only routes
Route::middleware(['auth:sanctum', 'role:Admin'])->group(function () {
    // Locations routes (only create, update, delete - index/show are public)
    Route::post('/locations', [LocationsController::class, 'store']);
    Route::put('/locations/{id}', [LocationsController::class, 'update']);
    Route::delete('/locations/{id}', [LocationsController::class, 'destroy']);
    // Guides routes (only create, update, delete - index/show are public)
    Route::post('/guides', [GuideController::class, 'store']);
    Route::put('/guides/{id}', [GuideController::class, 'update']);
    Route::delete('/guides/{id}', [GuideController::class, 'destroy']);
    // Shop Owners routes (only create, update, delete - index/show are public)
    Route::post('/shop-owners', [ShopOwnerController::class, 'store']);
    Route::put('/shop-owners/{id}', [ShopOwnerController::class, 'update']);
    Route::delete('/shop-owners/{id}', [ShopOwnerController::class, 'destroy']);
    // Shops routes (only create, update, delete - index/show are public)
    Route::post('/shops', [ShopController::class, 'store']);
    Route::put('/shops/{id}', [ShopController::class, 'update']);
    Route::delete('/shops/{id}', [ShopController::class, 'destroy']);
    // Hotel Owners routes (only create, update, delete - index/show are public)
    Route::post('/hotel-owners', [HotelOwnerController::class, 'store']);
    Route::put('/hotel-owners/{id}', [HotelOwnerController::class, 'update']);
    Route::delete('/hotel-owners/{id}', [HotelOwnerController::class, 'destroy']);
    // Hotels routes (only create, update, delete - index/show are public)
    Route::post('/hotels', [HotelController::class, 'store']);
    Route::put('/hotels/{id}', [HotelController::class, 'update']);
    Route::delete('/hotels/{id}', [HotelController::class, 'destroy']);
    // Vehicle Owners routes (only create, update, delete - index/show are public)
    Route::post('/vehicle-owners', [VehicleOwnerController::class, 'store']);
    Route::put('/vehicle-owners/{id}', [VehicleOwnerController::class, 'update']);
    Route::delete('/vehicle-owners/{id}', [VehicleOwnerController::class, 'destroy']);
    // Vehicles routes (only create, update, delete - index/show are public)
    Route::post('/vehicles', [VehicleController::class, 'store']);
    Route::put('/vehicles/{id}', [VehicleController::class, 'update']);
    Route::delete('/vehicles/{id}', [VehicleController::class, 'destroy']);
    // User management routes
    Route::get('/users', [AuthController::class, 'getUsers']);
    Route::post('/admin/users', [AuthController::class, 'registerUser']);
    Route::put('/admin/users/{id}', [AuthController::class, 'updateUser']);
    Route::delete('/admin/users/{id}', [AuthController::class, 'deleteUser']);
    // Request management routes
    Route::prefix('admin')->group(function () {
        Route::get('/role-requests', [AdminRoleRequestController::class, 'index']);
        Route::patch('/role-requests/{id}/approve', [AdminRoleRequestController::class, 'approve']);
        Route::patch('/role-requests/{id}/reject', [AdminRoleRequestController::class, 'reject']);
        Route::get('/role-requests/statistics', [AdminRoleRequestController::class, 'getStatistics']);
    });

    Route::apiResource('other-reviews', OtherReviewsController::class);
});