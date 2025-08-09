<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Guides;
use App\Http\Controllers\GuidesController;

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
    Route::post('/guides', [GuidesController::class, 'store']);
    Route::get('/guides', [GuidesController::class, 'getGuide']);

    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
