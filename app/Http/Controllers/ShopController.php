<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Shop;
use Illuminate\Support\Facades\Auth;

class ShopController extends Controller
{
    public function index()
    {
        $shops = Shop::all();
        return response()->json($shops);
    }

    public function show($id)
    {
        $shop = Shop::findOrFail($id);
        return response()->json($shop);
    }

    public function store(Request $request)
    {
        $request->validate([
        'shopName' => 'required|string|max:255',
        'shopAddress' => 'required|string|max:255',
        'description' => 'nullable|string',
        'locations' => 'nullable|array',
        'shopImage.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        'shop_owner_id' => 'required|exists:shop_owners,id',
        ]);

        $images =[];
        if ($request->hasFile('shopImage')) {
            $images = [];
            foreach ($request->file('shopImage') as $image) {
                $path = $image->store('shops', 'public'); 
                $images[] = $path;
            }
            $shopImagePaths = json_encode($images);
        } else {
            $shopImagePaths = json_encode([]);
        }

        

        $shop = Shop::create([
            'shopName' => $request->shopName,
            'shopAddress' => $request->shopAddress,
            'description' => $request->description,
            'locations' => json_encode($request->locations),
            'shopImage' => $shopImagePaths,
            'user_id' => Auth::id(),
            'shop_owner_id' => $request->shop_owner_id,
        ]);
        return response()->json([
            'message' => 'Shop created successfully!',
            'shop' => $shop
        ]);

    }
    public function update(Request $request, $id)
    {
        $request->validate([
            'shopName' => 'sometimes|required|string|max:255',
            'shopAddress' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'locations' => 'nullable|array',
            'shopImage.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $shop = Shop::findOrFail($id);

        // Keep existing images
        $images = $shop->shopImage ?? [];

        // Append new images if uploaded
        if ($request->hasFile('shopImage')) {
            foreach ($request->file('shopImage') as $image) {
                $path = $image->store('shops', 'public');
                $images[] = $path;
            }
        }

        $shop->update([
            'shopName'    => $request->shopName ?? $shop->shopName,
            'shopAddress' => $request->shopAddress ?? $shop->shopAddress,
            'description' => $request->description ?? $shop->description,
            'locations'   => $request->locations ?? $shop->locations, // no json_encode
            'shopImage'   => $images, // no json_encode
        ]);

        return response()->json([
            'message' => 'Shop updated successfully!',
            'shop' => $shop
        ]);
    }



    public function destroy($id)
    {
        $shop = Shop::findOrFail($id);
        $shop->delete();

        return response()->json(['message' => 'Shop deleted successfully!']);
    }

    public function getByLocation($location)
    {
        $shops = Shop::whereJsonContains('locations', $location)->get();
        return response()->json($shops);
    }
}
