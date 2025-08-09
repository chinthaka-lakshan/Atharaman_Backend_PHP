<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Guides;
use App\Models\ShopOwner;
use App\Models\HotelOwner;
use App\Models\VehicleOwner;
use App\Http\Controllers\Controller;
use App\Http\Controllers\GuidesController;
use App\Http\Controllers\ShopOwnerController;
use App\Http\Controllers\HotelOwnerController;
use App\Http\Controllers\VehicleOwnerController;

Route::post('/login', function (Request $request) {
    $credentials = $request->validate([
        'email' => 'required|email',
        'password' => 'required'
    ]);

    if (!Auth::attempt($credentials)) {
        return response()->json(['message' => 'Invalid login'], 401);
    }

    $user = Auth::user();
    $token = $user->createToken('api-token')->plainTextToken;

    return response()->json([
        'user' => $user,
        'token' => $token
    ]);
});


Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('guides', GuidesController::class);
    Route::get('guides/location/{location}', [GuidesController::class, 'getByLocation']);
    Route::apiResource('shop-owners', ShopOwnerController::class);
    Route::apiResource('hotel-owners', HotelOwnerController::class);
    Route::apiResource('vehicle-owners', VehicleOwnerController::class);
    Route::get('vehicle-owners/location/{location}', [VehicleOwnerController::class, 'getByLocation']);


    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
