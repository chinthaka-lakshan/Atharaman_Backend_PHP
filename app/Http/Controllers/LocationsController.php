<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Location;
use App\Models\LocationImage;
use App\Models\Guides;
use App\Models\Shop;
use App\Models\Hotel;
use App\Models\Vehicle;
use App\Models\Review;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class LocationsController extends Controller
{
    public function store(Request $request)
    {
        // Check if user is Admin
        if (Auth::user()->role !== 'Admin') {
            return response()->json(['error' => 'Unauthorized. Admin access required.'], 403);
        }

        $request->validate([
            'locationName' => ['required', 'string', 'max:500'],
            'shortDescription' => ['required', 'string', 'max:3000'],
            'longDescription' => ['required', 'string', 'max:10000'],
            'province' => ['required', 'string', 'max:200'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'locationType' => ['required', 'string', 'max:200'],
            'locationImage' => ['nullable', 'array', 'max:10'],
            'locationImage.*' => ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048']
        ]);

        // Start transaction for data consistency
        DB::beginTransaction();

        try {
            // Create location
            $location = Location::create([
                'locationName' => $request->locationName,
                'shortDescription' => $request->shortDescription,
                'longDescription' => $request->longDescription,
                'province' => $request->province,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'locationType' => $request->locationType
            ]);

            // Handle image uploads with location-specific folder
            if ($request->hasFile('locationImage')) {
                $this->processImages($location, $request->file('locationImage'));
            }

            DB::commit();

            // Load images for response
            $location->load('images');

            return response()->json([
                'message' => 'Location created successfully!',
                'location' => $location
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create location: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        // Check if user is admin
        if (Auth::user()->role !== 'Admin') {
            return response()->json(['error' => 'Unauthorized. Admin access required.'], 403);
        }

        $location = Location::with('images')->findOrFail($id);

        $validated = $request->validate([
            'locationName' => ['sometimes', 'required', 'string', 'max:500'],
            'shortDescription' => ['sometimes', 'required', 'string', 'max:3000'],
            'longDescription' => ['sometimes', 'required', 'string', 'max:10000'],
            'province' => ['sometimes', 'required', 'string', 'max:200'],
            'latitude' => ['sometimes', 'required', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'required', 'numeric', 'between:-180,180'],
            'locationType' => ['sometimes', 'required', 'string', 'max:200'],
            'locationImage' => ['sometimes', 'nullable', 'array', 'max:10'],
            'locationImage.*' => ['sometimes', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'removedImages' => ['sometimes', 'array'],
            'removedImages.*' => ['sometimes', 'integer', 'exists:location_images,id']
        ]);

        DB::beginTransaction();

        try {
            // Handle removed images FIRST
            if ($request->has('removedImages') && !empty($request->removedImages)) {
                $removedImages = LocationImage::where('location_id', $location->id)
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

            // Handle new image uploads with location-specific folder
            if ($request->hasFile('locationImage')) {
                $this->processImages($location, $request->file('locationImage'));
            }

            // Update location fields
            $location->fill($request->only([
                'locationName', 'shortDescription', 'longDescription',
                'province', 'latitude', 'longitude', 'locationType'
            ]));

            $location->save();

            // Reorder images to maintain consistent indexing
            $this->reorderImages($location->id);

            DB::commit();

            // Refresh with images
            $location->load('images');

            return response()->json([
                'message' => 'Location updated successfully!',
                'location' => $location
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update location: ' . $e->getMessage()], 500);
        }
    }

    // Add this to your controller temporarily
    public function checkLimits()
    {
        return response()->json([
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_file_uploads' => ini_get('max_file_uploads'),
            'memory_limit' => ini_get('memory_limit')
        ]);
    }

    public function destroy($id)
    {
        // Check if user is admin
        if (Auth::user()->role !== 'Admin') {
            return response()->json(['error' => 'Unauthorized. Admin access required.'], 403);
        }

        $location = Location::with('images')->findOrFail($id);

        DB::beginTransaction();

        try {
            // Delete the entire location folder from storage
            $locationFolder = "locations/{$location->id}";
            if (Storage::disk('public')->exists($locationFolder)) {
                Storage::disk('public')->deleteDirectory($locationFolder);
            }

            // Delete associated images from database
            $location->images()->delete();

            $location->delete();

            DB::commit();

            return response()->json(['message' => 'Location deleted successfully!']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to delete location: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Process and store images for a location in location-specific folder
     */
    private function processImages(Location $location, array $images)
    {
        $currentCount = $location->images()->count();
        
        // Validate image count (max 10)
        if (($currentCount + count($images)) > 10) {
            throw new \Exception('Maximum 10 images allowed. Current: ' . $currentCount);
        }

        $orderIndex = $location->images()->max('order_index') ?? -1;

        foreach ($images as $image) {
            $orderIndex++;
            
            // Store in location-specific folder: locations/{id}/filename.jpg
            $folder = "locations/{$location->id}";
            $filename = $this->generateUniqueFilename($image, $orderIndex);
            $path = $image->storeAs($folder, $filename, 'public');
            
            LocationImage::create([
                'location_id' => $location->id,
                'image_path' => $path,
                'order_index' => $orderIndex,
                'alt_text' => "{$location->locationName} - Image " . ($orderIndex + 1)
            ]);
        }
    }

    /**
     * Generate unique filename to avoid conflicts
     */
    private function generateUniqueFilename($image, $index)
    {
        $extension = $image->getClientOriginalExtension();
        $timestamp = time();
        return "image_{$index}_{$timestamp}.{$extension}";
    }

    /**
     * Helper method to reorder images
     */
    private function reorderImages($locationId)
    {
        $images = LocationImage::where('location_id', $locationId)
            ->orderBy('order_index')
            ->get();

        foreach ($images as $index => $image) {
            $image->update(['order_index' => $index]);
        }
    }

    public function show($id)
    {
        $location = Location::with(['images', 'reviews'])
                    ->withCount('reviews')
                    ->withAvg('reviews', 'rating')
                    ->findOrFail($id);
        
        return response()->json($location);
    }

    public function index()
    {
        $locations = Location::with(['images'])
                    ->withCount('reviews')
                    ->withAvg('reviews', 'rating')
                    ->get();
        
        return response()->json($locations);
    }

    public function getByProvince($province)
    {
        $locations = Location::where('province', $province)->get();

        if ($locations->isEmpty()) {
            return response()->json(['message' => 'No locations found for this province'], 404);
        }

        return response()->json([
            'locations' => $locations
        ]);
    }

    public function getByType($type)
    {
        $locations = Location::where('locationType', $type)->get();

        if ($locations->isEmpty()) {
            return response()->json(['message' => 'No locations found for this type'], 404);
        }

        return response()->json([
            'locations' => $locations
        ]);
    }

    // Get all related data for a specific location (guides, shops, hotels, vehicles)
    public function getRelatedData($id)
    {
        try {
            // Get the location to extract the location name
            $location = Location::findOrFail($id);
            $locationName = $location->locationName;
            
            // Fetch all related data
            $results = [
                'guides' => $this->getGuidesByLocation($locationName),
                'shops' => $this->getShopsByLocation($locationName),
                'hotels' => $this->getHotelsByLocation($locationName),
                'vehicles' => $this->getVehiclesByLocation($locationName),
            ];
            
            return response()->json([
                'success' => true,
                'data' => $results
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Location not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch location related data: ' . $e->getMessage()
            ], 500);
        }
    }

    // Helper methods
    private function getGuidesByLocation($locationName)
    {
        return Guides::where('locations', 'LIKE', "%{$locationName}%")
                    ->withCount('reviews')
                    ->withAvg('reviews', 'rating')
                    ->get();
    }

    private function getShopsByLocation($locationName)
    {
        return Shop::whereJsonContains('locations', $locationName)
                    ->withCount('reviews')
                    ->withAvg('reviews', 'rating')
                    ->get();
    }

    private function getHotelsByLocation($locationName)
    {
        return Hotel::whereJsonContains('locations', $locationName)
                    ->withCount('reviews')
                    ->withAvg('reviews', 'rating')
                    ->get();
    }

    private function getVehiclesByLocation($locationName)
    {
        return Vehicle::whereJsonContains('locations', $locationName)
                    ->withCount('reviews')
                    ->withAvg('reviews', 'rating')
                    ->get();
    }
}