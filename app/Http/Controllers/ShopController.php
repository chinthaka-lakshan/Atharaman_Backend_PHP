<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Shop;
use App\Models\ShopImage;
use App\Models\ShopOwner;
use App\Models\User;
use App\Models\Review;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ShopController extends Controller
{
    // Admin side methods
    public function store(Request $request)
    {
        $request->validate([
            'shop_name' => ['required', 'string', 'max:255'],
            'nearest_city' => ['required', 'string', 'max:100'],
            'shop_address' => ['nullable', 'string', 'max:500'],
            'contact_number' => ['required', 'string', 'max:15'],
            'whatsapp_number' => ['nullable', 'string', 'max:15'],
            'short_description' => ['required', 'string', 'max:1000'],
            'long_description' => ['nullable', 'string', 'max:10000'],
            'locations' => ['nullable', 'array'],
            'shop_owner_id' => ['required', 'exists:shop_owners,id'],
            'user_id' => ['required', 'exists:users,id'],
            'shopImage' => ['nullable', 'array', 'max:5'],
            'shopImage.*' => ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048']
        ]);

        // Start transaction for data consistency
        DB::beginTransaction();

        try {
            // Create shop
            $shop = Shop::create([
                'shop_name' => $request->shop_name,
                'nearest_city' => $request->nearest_city,
                'shop_address' => $request->shop_address,
                'contact_number' => $request->contact_number,
                'whatsapp_number' => $request->whatsapp_number,
                'short_description' => $request->short_description,
                'long_description' => $request->long_description,
                'locations' => $request->locations,
                'shop_owner_id' => $request->shop_owner_id,
                'user_id' => $request->user_id
            ]);

            // Handle image uploads with shop-specific folder
            if ($request->hasFile('shopImage')) {
                $this->processImages($shop, $request->file('shopImage'));
            }

            DB::commit();

            // Load images for response
            $shop->load('images');

            return response()->json([
                'message' => 'Shop created successfully!',
                'shop' => $shop
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create shop: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $shop = Shop::with('images')->findOrFail($id);

        $validated = $request->validate([
            'shop_name' => ['sometimes', 'required', 'string', 'max:255'],
            'nearest_city' => ['sometimes', 'required', 'string', 'max:100'],
            'shop_address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'contact_number' => ['sometimes', 'required', 'string', 'max:15'],
            'whatsapp_number' => ['sometimes', 'nullable', 'string', 'max:15'],
            'short_description' => ['sometimes', 'required', 'string', 'max:1000'],
            'long_description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'locations' => ['sometimes', 'nullable', 'array'],
            'shopImage' => ['sometimes', 'nullable', 'array', 'max:5'],
            'shopImage.*' => ['sometimes', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'removedImages' => ['sometimes', 'array'],
            'removedImages.*' => ['sometimes', 'integer', 'exists:shop_images,id']
        ]);

        DB::beginTransaction();

        try {
            // Handle removed images FIRST
            if ($request->has('removedImages') && !empty($request->removedImages)) {
                $removedImages = ShopImage::where('shop_id', $shop->id)
                    ->whereIn('id', $request->removedImages)
                    ->get();

                foreach ($removedImages as $image) {
                    // Delete from storage
                    if (Storage::disk('public')->exists($image->image_path)) {
                        Storage::disk('public')->delete($image->image_path);
                    }
                    // Delete from database
                    $image->delete();
                }
            }

            // Handle new image uploads with shop-specific folder
            if ($request->hasFile('shopImage')) {
                $this->processImages($shop, $request->file('shopImage'));
            }

            $shop->locations = $request->has('locations') ? $request->locations : null;
        
            // Update shop fields
            $shop->fill($request->only([
                'shop_name', 'nearest_city', 'shop_address', 'contact_number',
                'whatsapp_number', 'short_description', 'long_description'
            ]));

            $shop->save();

            // Reorder images to maintain consistent indexing
            $this->reorderImages($shop->id);

            DB::commit();

            // Refresh with images
            $shop->load('images');

            return response()->json([
                'message' => 'Shop updated successfully!',
                'shop' => $shop
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update shop: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $shop = Shop::with('images')->findOrFail($id);

        DB::beginTransaction();

        try {
            // Delete the entire shop folder from storage
            $shopFolder = "shops/{$shop->id}";
            if (Storage::disk('public')->exists($shopFolder)) {
                Storage::disk('public')->deleteDirectory($shopFolder);
            }

            // Delete associated images from database
            $shop->images()->delete();
            
            $shop->delete();

            DB::commit();

            return response()->json(['message' => 'Shop deleted successfully!']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to delete shop: ' . $e->getMessage()], 500);
        }
    }

    // User side methods
    public function getByAuthenticatedOwner(Request $request)
    {
        $shopOwner = ShopOwner::where('user_id', $request->user()->id)->firstOrFail();
        $shops = Shop::with('images')->where('shop_owner_id', $shopOwner->id)->get();
        
        return response()->json($shops);
    }

    public function storeByAuthenticatedOwner(Request $request)
    {
        $shopOwner = ShopOwner::where('user_id', $request->user()->id)->firstOrFail();

        $request->validate([
            'shop_name' => ['required', 'string', 'max:255'],
            'nearest_city' => ['required', 'string', 'max:100'],
            'shop_address' => ['nullable', 'string', 'max:500'],
            'contact_number' => ['required', 'string', 'max:15'],
            'whatsapp_number' => ['nullable', 'string', 'max:15'],
            'short_description' => ['required', 'string', 'max:1000'],
            'long_description' => ['nullable', 'string', 'max:10000'],
            'locations' => ['nullable', 'array'],
            'shop_owner_id' => ['required', 'exists:shop_owners,id'],
            'user_id' => ['required', 'exists:users,id'],
            'shopImage' => ['nullable', 'array', 'max:5'],
            'shopImage.*' => ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048']
        ]);

        DB::beginTransaction();

        try {
            // Create shop
            $shop = Shop::create([
                'shop_name' => $request->shop_name,
                'nearest_city' => $request->nearest_city,
                'shop_address' => $request->shop_address,
                'contact_number' => $request->contact_number,
                'whatsapp_number' => $request->whatsapp_number,
                'short_description' => $request->short_description,
                'long_description' => $request->long_description,
                'locations' => $request->locations,
                'shop_owner_id' => $request->shop_owner_id,
                'user_id' => $request->user_id
            ]);

            // Handle image uploads with shop-specific folder
            if ($request->hasFile('shopImage')) {
                $this->processImages($shop, $request->file('shopImage'));
            }

            DB::commit();

            // Load images for response
            $shop->load('images');

            return response()->json([
                'message' => 'Shop created successfully!',
                'shop' => $shop
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create shop: ' . $e->getMessage()], 500);
        }
    }

    public function updateByAuthenticatedOwner(Request $request, $id)
    {
        $shopOwner = ShopOwner::where('user_id', $request->user()->id)->firstOrFail();
        $shop = Shop::with('images')
                    ->where('shop_owner_id', $shopOwner->id)
                    ->findOrFail($id);

        $validated = $request->validate([
            'shop_name' => ['sometimes', 'required', 'string', 'max:255'],
            'nearest_city' => ['sometimes', 'required', 'string', 'max:100'],
            'shop_address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'contact_number' => ['sometimes', 'required', 'string', 'max:15'],
            'whatsapp_number' => ['sometimes', 'nullable', 'string', 'max:15'],
            'short_description' => ['sometimes', 'required', 'string', 'max:1000'],
            'long_description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'locations' => ['sometimes', 'nullable', 'array'],
            'shopImage' => ['sometimes', 'nullable', 'array', 'max:5'],
            'shopImage.*' => ['sometimes', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'removedImages' => ['sometimes', 'array'],
            'removedImages.*' => ['sometimes', 'integer', 'exists:shop_images,id']
        ]);

        DB::beginTransaction();

        try {
            // Handle removed images FIRST
            if ($request->has('removedImages') && !empty($request->removedImages)) {
                $removedImages = ShopImage::where('shop_id', $shop->id)
                    ->whereIn('id', $request->removedImages)
                    ->get();

                foreach ($removedImages as $image) {
                    // Delete from storage
                    if (Storage::disk('public')->exists($image->image_path)) {
                        Storage::disk('public')->delete($image->image_path);
                    }
                    // Delete from database
                    $image->delete();
                }
            }

            // Handle new image uploads with shop-specific folder
            if ($request->hasFile('shopImage')) {
                $this->processImages($shop, $request->file('shopImage'));
            }

            $shop->locations = $request->has('locations') ? $request->locations : null;
        
            // Update shop fields
            $shop->fill($request->only([
                'shop_name', 'nearest_city', 'shop_address', 'contact_number',
                'whatsapp_number', 'short_description', 'long_description'
            ]));

            $shop->save();

            // Reorder images to maintain consistent indexing
            $this->reorderImages($shop->id);

            DB::commit();

            // Refresh with images
            $shop->load('images');

            return response()->json([
                'message' => 'Shop updated successfully!',
                'shop' => $shop
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update shop: ' . $e->getMessage()], 500);
        }
    }

    public function deleteByAuthenticatedOwner(Request $request, $id)
    {
        $shopOwner = ShopOwner::where('user_id', $request->user()->id)->firstOrFail();
        $shop = Shop::with('images')
                    ->where('shop_owner_id', $shopOwner->id)
                    ->findOrFail($id);
        
        DB::beginTransaction();

        try {
            // Delete the entire shop folder from storage
            $shopFolder = "shops/{$shop->id}";
            if (Storage::disk('public')->exists($shopFolder)) {
                Storage::disk('public')->deleteDirectory($shopFolder);
            }

            // Delete associated images from database
            $shop->images()->delete();
            
            $shop->delete();

            DB::commit();

            return response()->json(['message' => 'Shop deleted successfully!']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to delete shop: ' . $e->getMessage()], 500);
        }
    }

    // Process and store images for a shop in shop-specific folder
    private function processImages(Shop $shop, array $images)
    {
        $currentCount = $shop->images()->count();

        // Validate image count (max 5)
        if (($currentCount + count($images)) > 5) {
            throw new \Exception('Maximum 5 images allowed. Current: ' . $currentCount);
        }

        $orderIndex = $shop->images()->max('order_index') ?? -1;

        foreach ($images as $image) {
            $orderIndex++;

            // Store in shop-specific folder: shop/{id}/filename.jpg
            $folder = "shops/{$shop->id}";
            $filename = $this->generateUniqueFilename($image, $orderIndex);
            $path = $image->storeAs($folder, $filename, 'public');

            ShopImage::create([
                'shop_id' => $shop->id,
                'image_path' => $path,
                'order_index' => $orderIndex,
                'alt_text' => "{$shop->shop_name} - Image " . ($orderIndex + 1)
            ]);
        }
    }

    // Generate unique filename to avoid conflicts
    private function generateUniqueFilename($image, $index)
    {
        $extension = $image->getClientOriginalExtension();
        $timestamp = time();
        return "image_{$index}_{$timestamp}.{$extension}";
    }

    // Helper method to reorder images
    private function reorderImages($shopId)
    {
        $images = ShopImage::where('shop_id', $shopId)
            ->orderBy('order_index')
            ->get();

        foreach ($images as $index => $image) {
            $image->update(['order_index' => $index]);
        }
    }

    // Public methods
    public function show($id)
    {
        $shop = Shop::with(['images', 'reviews'])
                    ->withCount('reviews')
                    ->withAvg('reviews', 'rating')
                    ->findOrFail($id);

        return response()->json($shop);
    }

    public function index()
    {
        $shops = Shop::with('images')
                    ->withCount('reviews')
                    ->withAvg('reviews', 'rating')
                    ->get();

        return response()->json($shops);
    }

    public function getByOwner($ownerId)
    {
        $shops = Shop::with('images')
                    ->where('shop_owner_id', $ownerId)
                    ->get();

        return response()->json($shops);
    }

    public function getByLocation($location)
    {
        $shops = Shop::with('images')
                    ->whereJsonContains('locations', $location)
                    ->get();

        return response()->json($shops);
    }
}