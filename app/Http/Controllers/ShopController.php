<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Shop;
use Illuminate\Support\Facades\Auth;

class ShopController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'shopName' => 'required|string|max:255',
            'shopAddress' => 'required|string|max:255',
            'description' => 'nullable|string',
            'locations' => 'nullable|array',
            'shopImage.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'shop_owner_id' => 'required|exists:shop_owners,id',
            'user_id' => 'required|exists:users,id'
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
            'shopImage' => $shopImagePaths,
            'locations' => json_encode($request->locations),
            'user_id' => $request->user_id,
            'shop_owner_id' => $request->shop_owner_id,
        ]);

        return response()->json([
            'message' => 'Shop created successfully!',
            'shop' => $shop
        ]);
    }

    public function update(Request $request, $id)
    {
        $shop = Shop::findOrFail($id);
        $existingImages = json_decode($shop->shopImage, true) ?? [];

        $validated = $request->validate([
            'shopName' => 'sometimes|required|string|max:255',
            'shopAddress' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'locations' => 'nullable|array',
            'shopImage.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'user_id' => 'sometimes|required|exists:users,id',
            'shop_owner_id' => 'sometimes|required|exists:shop_owners,id'
        ]);

        // Handle images (support both single and multiple file upload)
        $existingImages = json_decode($shop->shopImage, true) ?? [];
        $newImages = [];
        if ($request->hasFile('shopImage')) {
            $files = $request->file('shopImage');
            if (!is_array($files)) {
                $files = [$files];
            }
            foreach ($files as $image) {
                $path = $image->store('shops', 'public');
                $newImages[] = $path;
            }
        }
        $shop->shopImage = count($newImages) > 0 ? json_encode($newImages) : json_encode($existingImages);

        $shop->fill($request->only([
            'shopName', 'shopAddress', 'description'
        ]));

        if ($request->has('locations')) {
            $shop->locations = json_encode($request->locations);
        }

        $shop->save();

        return response()->json([
            'message' => 'Shop updated successfully!',
            'shop' => $shop->fresh()
        ]);
    }

    public function show($id)
    {
        $shop = Shop::findOrFail($id);
        return response()->json($shop);
    }

    public function index()
    {
        $shops = Shop::all();
        return response()->json($shops);
    }

    public function getByLocation($location)
    {
        $shops = Shop::whereJsonContains('locations', $location)->get();
        return response()->json($shops);
    }

    public function destroy($id)
    {
        $shop = Shop::findOrFail($id);

        // Delete associated images
        $images = json_decode($shop->shopImage, true) ?? [];
        foreach ($images as $image) {
            \Storage::disk('public')->delete($image);
        }

        $shop->delete();

        return response()->json(['message' => 'Shop deleted successfully!']);
    }
}
