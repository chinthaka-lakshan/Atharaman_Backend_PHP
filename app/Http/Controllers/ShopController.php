<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Shop;
use App\Models\ShopOwner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ShopController extends Controller
{
    // Admin side methods
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
        }

        $shop = Shop::create([
            'shopName' => $request->shopName,
            'shopAddress' => $request->shopAddress,
            'description' => $request->description,
            'shopImage' => $images,
            'locations' => $request->locations,
            'user_id' => $request->user_id,
            'shop_owner_id' => $request->shop_owner_id
        ]);

        return response()->json([
            'message' => 'Shop created successfully!',
            'shop' => $shop
        ]);
    }

    public function update(Request $request, $id)
    {
        $shop = Shop::findOrFail($id);
        $existingImages = $shop->shopImage ?? [];

        $validated = $request->validate([
            'shopName' => 'sometimes|required|string|max:255',
            'shopAddress' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'locations' => 'nullable|array',
            'shopImage.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'user_id' => 'sometimes|required|exists:users,id',
            'shop_owner_id' => 'sometimes|required|exists:shop_owners,id'
        ]);

        // Handle image updates
        if ($request->hasFile('shopImage')) {
            // Delete existing images from storage
            foreach ($existingImages as $oldImage) {
                \Storage::disk('public')->delete($oldImage);
            }
            // Store new images
            $newImages = [];
            foreach ($request->file('shopImage') as $image) {
                $path = $image->store('shops', 'public');
                $newImages[] = $path;
            }
            $shop->shopImage = $newImages;
        } elseif ($request->input('remove_images') === 'true') {
            // Explicit request to remove all images
            foreach ($existingImages as $oldImage) {
                \Storage::disk('public')->delete($oldImage);
            }
            $shop->shopImage = [];
        }
        
        // Update other fields
        $shop->fill($request->only([
            'shopName', 'shopAddress', 'description'
        ]));

        if ($request->has('locations')) {
            $shop->locations = $request->locations;
        }

        $shop->save();

        return response()->json([
            'message' => 'Shop updated successfully!',
            'shop' => $shop->fresh()
        ]);
    }

    public function destroy($id)
    {
        $shop = Shop::findOrFail($id);

        // Delete associated images
        $images = $shop->shopImage ?? [];
        foreach ($images as $image) {
            \Storage::disk('public')->delete($image);
        }

        $shop->delete();

        return response()->json(['message' => 'Shop deleted successfully!']);
    }

    // User side methods
    public function getByAuthenticatedOwner(Request $request)
    {
        $shopOwner = ShopOwner::where('user_id', $request->user()->id)->firstOrFail();
        $shops = Shop::where('shop_owner_id', $shopOwner->id)->get();
        
        return response()->json($shops);
    }

    public function storeByAuthenticatedOwner(Request $request)
    {
        $shopOwner = ShopOwner::where('user_id', $request->user()->id)->firstOrFail();

        $request->validate([
            'shopName' => 'required|string|max:255',
            'shopAddress' => 'required|string|max:255',
            'description' => 'nullable|string',
            'locations' => 'nullable|array',
            'shopImage.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $images = [];
        if ($request->hasFile('shopImage')) {
            foreach ($request->file('shopImage') as $image) {
                $path = $image->store('shops', 'public'); 
                $images[] = $path;
            }
        }

        $shop = Shop::create([
            'shopName' => $request->shopName,
            'shopAddress' => $request->shopAddress,
            'description' => $request->description,
            'shopImage' => $images,
            'locations' => $request->locations,
            'user_id' => $request->user()->id,
            'shop_owner_id' => $shopOwner->id
        ]);

        return response()->json([
            'message' => 'Shop created successfully!',
            'shop' => $shop
        ]);
    }

    public function updateByAuthenticatedOwner(Request $request, $id)
    {
        $shopOwner = ShopOwner::where('user_id', $request->user()->id)->firstOrFail();
        $shop = Shop::where('id', $id)
                    ->where('shop_owner_id', $shopOwner->id)
                    ->firstOrFail();
        
        $existingImages = $shop->shopImage ?? [];

        $validated = $request->validate([
            'shopName' => 'sometimes|required|string|max:255',
            'shopAddress' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'locations' => 'nullable|array',
            'shopImage.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Handle image updates
        if ($request->hasFile('shopImage')) {
            // Delete existing images from storage
            foreach ($existingImages as $oldImage) {
                Storage::disk('public')->delete($oldImage);
            }
            // Store new images
            $newImages = [];
            foreach ($request->file('shopImage') as $image) {
                $path = $image->store('shops', 'public');
                $newImages[] = $path;
            }
            $shop->shopImage = $newImages;
        } elseif ($request->input('remove_images') === 'true') {
            // Explicit request to remove all images
            foreach ($existingImages as $oldImage) {
                Storage::disk('public')->delete($oldImage);
            }
            $shop->shopImage = [];
        }
        
        // Update other fields
        $shop->fill($request->only([
            'shopName', 'shopAddress', 'description'
        ]));

        if ($request->has('locations')) {
            $shop->locations = $request->locations;
        }

        $shop->save();

        return response()->json([
            'message' => 'Shop updated successfully!',
            'shop' => $shop->fresh()
        ]);
    }

    public function deleteByAuthenticatedOwner(Request $request, $id)
    {
        $shopOwner = ShopOwner::where('user_id', $request->user()->id)->firstOrFail();
        $shop = Shop::where('id', $id)
                    ->where('shop_owner_id', $shopOwner->id)
                    ->firstOrFail();
        
        // Delete associated images
        $images = $shop->shopImage ?? [];
        foreach ($images as $image) {
            \Storage::disk('public')->delete($image);
        }

        $shop->delete();

        return response()->json(['message' => 'Shop deleted successfully!']);
    }

    // Public methods
    public function show($id)
    {
        $shop = Shop::withCount('reviews')
                    ->withAvg('reviews', 'rating')
                    ->with('reviews')
                    ->findOrFail($id);

        return response()->json($shop);
    }

    public function index()
    {
        $shops = Shop::withCount('reviews')
                    ->withAvg('reviews', 'rating')
                    ->with('reviews')
                    ->get();

        return response()->json($shops);
    }

    public function getByOwner($ownerId)
    {
        $shops = Shop::where('shop_owner_id', $ownerId)->get();
        return response()->json($shops);
    }

    public function getByLocation($location)
    {
        $shops = Shop::whereJsonContains('locations', $location)->get();
        return response()->json($shops);
    }
}