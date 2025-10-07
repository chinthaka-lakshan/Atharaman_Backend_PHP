<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Hotel;
use App\Models\HotelImage;
use App\Models\HotelOwner;
use App\Models\User;
use App\Models\Review;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class HotelController extends Controller
{
    // Admin side methods
    public function store(Request $request)
    {
        $request->validate([
            'hotel_name' => ['required', 'string', 'max:255'],
            'nearest_city' => ['required', 'string', 'max:100'],
            'hotel_address' => ['required', 'string', 'max:500'],
            'business_mail' => ['nullable', 'email'],
            'contact_number' => ['required', 'string', 'max:15'],
            'whatsapp_number' => ['nullable', 'string', 'max:15'],
            'short_description' => ['required', 'string', 'max:1000'],
            'long_description' => ['nullable', 'string', 'max:10000'],
            'locations' => ['nullable', 'array'],
            'hotel_owner_id' => ['required', 'exists:hotel_owners,id'],
            'user_id' => ['required', 'exists:users,id'],
            'hotelImage' => ['nullable', 'array', 'max:5'],
            'hotelImage.*' => ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048']
        ]);

        // Start transaction for data consistency
        DB::beginTransaction();

        try {
            // Create hotel
            $hotel = Hotel::create([
                'hotel_name' => $request->hotel_name,
                'nearest_city' => $request->nearest_city,
                'hotel_address' => $request->hotel_address,
                'business_mail' => $request->business_mail,
                'contact_number' => $request->contact_number,
                'whatsapp_number' => $request->whatsapp_number,
                'short_description' => $request->short_description,
                'long_description' => $request->long_description,
                'locations' => $request->locations,
                'hotel_owner_id' => $request->hotel_owner_id,
                'user_id' => $request->user_id
            ]);

            // Handle image uploads with hotel-specific folder
            if ($request->hasFile('hotelImage')) {
                $this->processImages($hotel, $request->file('hotelImage'));
            }

            DB::commit();

            // Load images for response
            $hotel->load('images');

            return response()->json([
                'message' => 'Hotel created successfully!',
                'hotel' => $hotel
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create hotel: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $hotel = Hotel::with('images')->findOrFail($id);

        $validated = $request->validate([
            'hotel_name' => ['sometimes', 'required', 'string', 'max:255'],
            'nearest_city' => ['sometimes', 'required', 'string', 'max:100'],
            'hotel_address' => ['sometimes', 'required', 'string', 'max:500'],
            'business_mail' => ['sometimes', 'nullable', 'email'],
            'contact_number' => ['sometimes', 'required', 'string', 'max:15'],
            'whatsapp_number' => ['sometimes', 'nullable', 'string', 'max:15'],
            'short_description' => ['sometimes', 'required', 'string', 'max:1000'],
            'long_description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'locations' => ['sometimes', 'nullable', 'array'],
            'hotelImage' => ['sometimes', 'nullable', 'array', 'max:5'],
            'hotelImage.*' => ['sometimes', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'removedImages' => ['sometimes', 'array'],
            'removedImages.*' => ['sometimes', 'integer', 'exists:hotel_images,id']
        ]);

        DB::beginTransaction();

        try {
            // Handle removed images FIRST
            if ($request->has('removedImages') && !empty($request->removedImages)) {
                $removedImages = HotelImage::where('hotel_id', $hotel->id)
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

            // Handle new image uploads with hotel-specific folder
            if ($request->hasFile('hotelImage')) {
                $this->processImages($hotel, $request->file('hotelImage'));
            }

            $hotel->locations = $request->has('locations') ? $request->locations : null;
        
            // Update hotel fields
            $hotel->fill($request->only([
                'hotel_name', 'nearest_city', 'hotel_address',
                'business_mail', 'contact_number', 'whatsapp_number',
                'short_description', 'long_description'
            ]));

            $hotel->save();

            // Reorder images to maintain consistent indexing
            $this->reorderImages($hotel->id);

            DB::commit();

            // Refresh with images
            $hotel->load('images');

            return response()->json([
                'message' => 'Hotel updated successfully!',
                'hotel' => $hotel
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update hotel: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $hotel = Hotel::with('images')->findOrFail($id);

        DB::beginTransaction();

        try {
            // Delete the entire hotel folder from storage
            $hotelFolder = "hotels/{$hotel->id}";
            if (Storage::disk('public')->exists($hotelFolder)) {
                Storage::disk('public')->deleteDirectory($hotelFolder);
            }

            // Delete associated images from database
            $hotel->images()->delete();
            
            $hotel->delete();

            DB::commit();

            return response()->json(['message' => 'Hotel deleted successfully!']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to delete hotel: ' . $e->getMessage()], 500);
        }
    }

    // User side methods
    public function getByAuthenticatedOwner(Request $request)
    {
        $hotelOwner = HotelOwner::where('user_id', $request->user()->id)->firstOrFail();
        $hotels = Hotel::with('images')->where('hotel_owner_id', $hotelOwner->id)->get();
        
        return response()->json($hotels);
    }

    public function storeByAuthenticatedOwner(Request $request)
    {
        $hotelOwner = HotelOwner::where('user_id', $request->user()->id)->firstOrFail();

        $request->validate([
            'hotel_name' => ['required', 'string', 'max:255'],
            'nearest_city' => ['required', 'string', 'max:100'],
            'hotel_address' => ['required', 'string', 'max:500'],
            'business_mail' => ['nullable', 'email'],
            'contact_number' => ['required', 'string', 'max:15'],
            'whatsapp_number' => ['nullable', 'string', 'max:15'],
            'short_description' => ['required', 'string', 'max:1000'],
            'long_description' => ['nullable', 'string', 'max:10000'],
            'locations' => ['nullable', 'array'],
            'hotel_owner_id' => ['required', 'exists:hotel_owners,id'],
            'user_id' => ['required', 'exists:users,id'],
            'hotelImage' => ['nullable', 'array', 'max:5'],
            'hotelImage.*' => ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048']
        ]);

        DB::beginTransaction();

        try {
            // Create hotel
            $hotel = Hotel::create([
                'hotel_name' => $request->hotel_name,
                'nearest_city' => $request->nearest_city,
                'hotel_address' => $request->hotel_address,
                'business_mail' => $request->business_mail,
                'contact_number' => $request->contact_number,
                'whatsapp_number' => $request->whatsapp_number,
                'short_description' => $request->short_description,
                'long_description' => $request->long_description,
                'locations' => $request->locations,
                'hotel_owner_id' => $request->hotel_owner_id,
                'user_id' => $request->user_id
            ]);

            // Handle image uploads with hotel-specific folder
            if ($request->hasFile('hotelImage')) {
                $this->processImages($hotel, $request->file('hotelImage'));
            }

            DB::commit();

            // Load images for response
            $hotel->load('images');

            return response()->json([
                'message' => 'Hotel created successfully!',
                'hotel' => $hotel
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create hotel: ' . $e->getMessage()], 500);
        }
    }

    public function updateByAuthenticatedOwner(Request $request, $id)
    {
        $hotelOwner = HotelOwner::where('user_id', $request->user()->id)->firstOrFail();
        $hotel = Hotel::with('images')
                    ->where('hotel_owner_id', $hotelOwner->id)
                    ->firstOrFail($id);

        $validated = $request->validate([
            'hotel_name' => ['sometimes', 'required', 'string', 'max:255'],
            'nearest_city' => ['sometimes', 'required', 'string', 'max:100'],
            'hotel_address' => ['sometimes', 'required', 'string', 'max:500'],
            'business_mail' => ['sometimes', 'nullable', 'email'],
            'contact_number' => ['sometimes', 'required', 'string', 'max:15'],
            'whatsapp_number' => ['sometimes', 'nullable', 'string', 'max:15'],
            'short_description' => ['sometimes', 'required', 'string', 'max:1000'],
            'long_description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'locations' => ['sometimes', 'nullable', 'array'],
            'hotelImage' => ['sometimes', 'nullable', 'array', 'max:5'],
            'hotelImage.*' => ['sometimes', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'removedImages' => ['sometimes', 'array'],
            'removedImages.*' => ['sometimes', 'integer', 'exists:hotel_images,id']
        ]);

        DB::beginTransaction();

        try {
            // Handle removed images FIRST
            if ($request->has('removedImages') && !empty($request->removedImages)) {
                $removedImages = HotelImage::where('hotel_id', $hotel->id)
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

            // Handle new image uploads with hotel-specific folder
            if ($request->hasFile('hotelImage')) {
                $this->processImages($hotel, $request->file('hotelImage'));
            }

            $hotel->locations = $request->has('locations') ? $request->locations : null;
        
            // Update hotel fields
            $hotel->fill($request->only([
                'hotel_name', 'nearest_city', 'hotel_address',
                'business_mail', 'contact_number', 'whatsapp_number',
                'short_description', 'long_description'
            ]));

            $hotel->save();

            // Reorder images to maintain consistent indexing
            $this->reorderImages($hotel->id);

            DB::commit();

            // Refresh with images
            $hotel->load('images');

            return response()->json([
                'message' => 'Hotel updated successfully!',
                'hotel' => $hotel
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update hotel: ' . $e->getMessage()], 500);
        }
    }

    public function deleteByAuthenticatedOwner(Request $request, $id)
    {
        $hotelOwner = HotelOwner::where('user_id', $request->user()->id)->firstOrFail();
        $hotel = Hotel::with('images')
                    ->where('hotel_owner_id', $hotelOwner->id)
                    ->firstOrFail($id);

        DB::beginTransaction();

        try {
            // Delete the entire hotel folder from storage
            $hotelFolder = "hotels/{$hotel->id}";
            if (Storage::disk('public')->exists($hotelFolder)) {
                Storage::disk('public')->deleteDirectory($hotelFolder);
            }

            // Delete associated images from database
            $hotel->images()->delete();
            
            $hotel->delete();

            DB::commit();

            return response()->json(['message' => 'Hotel deleted successfully!']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to delete hotel: ' . $e->getMessage()], 500);
        }
    }

    // Process and store images for a hotel in hotel-specific folder
    private function processImages(Hotel $hotel, array $images)
    {
        $currentCount = $hotel->images()->count();

        // Validate image count (max 5)
        if (($currentCount + count($images)) > 5) {
            throw new \Exception('Maximum 5 images allowed. Current: ' . $currentCount);
        }

        $orderIndex = $hotel->images()->max('order_index') ?? -1;

        foreach ($images as $image) {
            $orderIndex++;

            // Store in hotel-specific folder: hotel/{id}/filename.jpg
            $folder = "hotels/{$hotel->id}";
            $filename = $this->generateUniqueFilename($image, $orderIndex);
            $path = $image->storeAs($folder, $filename, 'public');

            HotelImage::create([
                'hotel_id' => $hotel->id,
                'image_path' => $path,
                'order_index' => $orderIndex,
                'alt_text' => "{$hotel->hotel_name} - Image " . ($orderIndex + 1)
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
    private function reorderImages($hotelId)
    {
        $images = HotelImage::where('hotel_id', $hotelId)
            ->orderBy('order_index')
            ->get();

        foreach ($images as $index => $image) {
            $image->update(['order_index' => $index]);
        }
    }

    // Public methods
    public function show($id)
    {
        $hotel = Hotel::with(['images', 'reviews'])
                    ->withCount('reviews')
                    ->withAvg('reviews', 'rating')
                    ->findOrFail($id);

        return response()->json($hotel);
    }

    public function index()
    {
        $hotels = Hotel::with('images')
                    ->withCount('reviews')
                    ->withAvg('reviews', 'rating')
                    ->get();

        return response()->json($hotels);
    }

    public function getByOwner($ownerId)
    {
        $hotels = Hotel::with('images')
                    ->where('hotel_owner_id', $ownerId)
                    ->get();

        return response()->json($hotels);
    }

    public function getByLocation($location)
    {
        $hotels = Hotel::with('images')
                    ->whereJsonContains('locations', $location)
                    ->get();

        return response()->json($hotels);
    }
}