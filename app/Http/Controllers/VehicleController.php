<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Vehicle;
use App\Models\VehicleImage;
use App\Models\VehicleOwner;
use App\Models\User;
use App\Models\Review;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class VehicleController extends Controller
{
    // Admin side methods
    public function store(Request $request)
    {
        $request->validate([
            'vehicle_name' => ['required', 'string', 'max:255'],
            'vehicle_type' => ['required', 'string', 'max:50'],
            'reg_number' => ['required', 'string', 'max:24', 'regex:/^[A-Z0-9]{2,3}([\-\s]?[0-9]{3,4})?$/'],
            'manufactured_year' => ['nullable', 'digits:4', 'integer', 'min:1900', 'max:'.(date('Y')+1)],
            'no_of_passengers' => ['required', 'integer', 'min:1', 'max:100'],
            'fuel_type' => ['required', 'string', 'max:24'],
            'driver_status' => ['required', 'string', 'max:50'],
            'short_description' => ['required', 'string', 'max:1000'],
            'long_description' => ['nullable', 'string', 'max:10000'],
            'price_per_day' => ['nullable', 'numeric', 'min:0'],
            'mileage_per_day' => ['nullable', 'integer', 'min:0'],
            'locations' => ['nullable', 'array'],
            'vehicle_owner_id' => ['required', 'exists:vehicle_owners,id'],
            'user_id' => ['required', 'exists:users,id'],
            'vehicleImage' => ['nullable', 'array', 'max:5'],
            'vehicleImage.*' => ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048']
        ]);

        // Normalize and validate registration number
        $normalizedRegNumber = $this->normalizeRegistrationNumber($request->reg_number);
        
        // Check if normalized registration number already exists
        $existingVehicle = Vehicle::where('reg_number', $normalizedRegNumber)->first();
        if ($existingVehicle) {
            return response()->json(['error' => 'This registration number is already registered in our system'], 422);
        }

        // Start transaction for data consistency
        DB::beginTransaction();

        try {
            // Create vehicle
            $vehicle = Vehicle::create([
                'vehicle_name' => $request->vehicle_name,
                'vehicle_type' => $request->vehicle_type,
                'reg_number' => $normalizedRegNumber,
                'manufactured_year' => $request->manufactured_year,
                'no_of_passengers' => $request->no_of_passengers,
                'fuel_type' => $request->fuel_type,
                'driver_status' => $request->driver_status,
                'short_description' => $request->short_description,
                'long_description' => $request->long_description,
                'price_per_day' => $request->filled('price_per_day') ? $request->price_per_day : null,
                'mileage_per_day' => $request->filled('mileage_per_day') ? $request->mileage_per_day : null,
                'locations' => $request->locations,
                'vehicle_owner_id' => $request->vehicle_owner_id,
                'user_id' => $request->user_id
            ]);

            // Handle image uploads with vehicle-specific folder
            if ($request->hasFile('vehicleImage')) {
                $this->processImages($vehicle, $request->file('vehicleImage'));
            }

            DB::commit();

            // Load images for response
            $vehicle->load('images');

            return response()->json([
                'message' => 'Vehicle created successfully!',
                'vehicle' => $vehicle
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create vehicle: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $vehicle = Vehicle::with('images')->findOrFail($id);

        $validated = $request->validate([
            'vehicle_name' => ['sometimes', 'required', 'string', 'max:255'],
            'vehicle_type' => ['sometimes', 'required', 'string', 'max:50'],
            'reg_number' => ['sometimes', 'required', 'string', 'max:24', 'regex:/^[A-Z0-9]{2,3}([\-\s]?[0-9]{3,4})?$/'],
            'manufactured_year' => ['sometimes', 'nullable', 'digits:4', 'integer', 'min:1900', 'max:'.(date('Y')+1)],
            'no_of_passengers' => ['sometimes', 'required', 'integer', 'min:1', 'max:100'],
            'fuel_type' => ['sometimes', 'required', 'string', 'max:24'],
            'driver_status' => ['sometimes', 'required', 'string', 'max:50'],
            'short_description' => ['sometimes', 'required', 'string', 'max:1000'],
            'long_description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'price_per_day' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'mileage_per_day' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'locations' => ['sometimes', 'nullable', 'array'],
            'vehicleImage' => ['sometimes', 'nullable', 'array', 'max:5'],
            'vehicleImage.*' => ['sometimes', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'removedImages' => ['sometimes', 'array'],
            'removedImages.*' => ['sometimes', 'integer', 'exists:vehicle_images,id']
        ]);

        DB::beginTransaction();

        try {
            // Handle removed images FIRST
            if ($request->has('removedImages') && !empty($request->removedImages)) {
                $removedImages = VehicleImage::where('vehicle_id', $vehicle->id)
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

            // Handle new image uploads with vehicle-specific folder
            if ($request->hasFile('vehicleImage')) {
                $this->processImages($vehicle, $request->file('vehicleImage'));
            }

            // Normalize and validate registration number if it's being updated
            if ($request->has('reg_number')) {
                $normalizedRegNumber = $this->normalizeRegistrationNumber($request->reg_number);
                
                // Check if normalized reg_number already exists (excluding current vehicle)
                if ($normalizedRegNumber !== $vehicle->reg_number) {
                    $existingVehicle = Vehicle::where('reg_number', $normalizedRegNumber)
                        ->where('id', '!=', $id)
                        ->first();
                        
                    if ($existingVehicle) {
                        return response()->json(['error' => 'This registration number is already registered in our system'], 422);
                    }
                    
                    $vehicle->reg_number = $normalizedRegNumber;
                }
            }

            $vehicle->locations = $request->has('locations') ? $request->locations : null;
        
            // Update vehicle fields
            $updateData = $request->only([
                'vehicle_name', 'vehicle_type', 'manufactured_year',
                'no_of_passengers', 'fuel_type', 'driver_status',
                'short_description', 'long_description'
            ]);

            // Handle nullable numeric fields specifically
            $updateData['price_per_day'] = $request->filled('price_per_day') ? $request->price_per_day : null;
            $updateData['mileage_per_day'] = $request->filled('mileage_per_day') ? $request->mileage_per_day : null;

            $vehicle->fill($updateData);
            $vehicle->save();

            // Reorder images to maintain consistent indexing
            $this->reorderImages($vehicle->id);

            DB::commit();

            // Refresh with images
            $vehicle->load('images');

            return response()->json([
                'message' => 'Vehicle updated successfully!',
                'vehicle' => $vehicle
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update vehicle: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $vehicle = Vehicle::with('images')->findOrFail($id);

        DB::beginTransaction();

        try {
            // Delete the entire vehicle folder from storage
            $vehicleFolder = "vehicles/{$vehicle->id}";
            if (Storage::disk('public')->exists($vehicleFolder)) {
                Storage::disk('public')->deleteDirectory($vehicleFolder);
            }

            // Delete associated images from database
            $vehicle->images()->delete();
            
            $vehicle->delete();

            DB::commit();

            return response()->json(['message' => 'Vehicle deleted successfully!']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to delete vehicle: ' . $e->getMessage()], 500);
        }
    }

    // User side methods
    public function getByAuthenticatedOwner(Request $request)
    {
        $vehicleOwner = VehicleOwner::where('user_id', $request->user()->id)->firstOrFail();
        $vehicles = Vehicle::with('images')->where('vehicle_owner_id', $vehicleOwner->id)->get();
        
        return response()->json($vehicles);
    }

    public function storeByAuthenticatedOwner(Request $request)
    {
        $vehicleOwner = VehicleOwner::where('user_id', $request->user()->id)->firstOrFail();

        $request->validate([
            'vehicle_name' => ['required', 'string', 'max:255'],
            'vehicle_type' => ['required', 'string', 'max:50'],
            'reg_number' => ['required', 'string', 'max:24', 'regex:/^[A-Z0-9]{2,3}([\-\s]?[0-9]{3,4})?$/'],
            'manufactured_year' => ['nullable', 'digits:4', 'integer', 'min:1900', 'max:'.(date('Y')+1)],
            'no_of_passengers' => ['required', 'integer', 'min:1', 'max:100'],
            'fuel_type' => ['required', 'string', 'max:24'],
            'driver_status' => ['required', 'string', 'max:50'],
            'short_description' => ['required', 'string', 'max:1000'],
            'long_description' => ['nullable', 'string', 'max:10000'],
            'price_per_day' => ['nullable', 'numeric', 'min:0'],
            'mileage_per_day' => ['nullable', 'integer', 'min:0'],
            'locations' => ['nullable', 'array'],
            'vehicle_owner_id' => ['required', 'exists:vehicle_owners,id'],
            'user_id' => ['required', 'exists:users,id'],
            'vehicleImage' => ['nullable', 'array', 'max:5'],
            'vehicleImage.*' => ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048']
        ]);

        // Normalize and validate registration number
        $normalizedRegNumber = $this->normalizeRegistrationNumber($request->reg_number);
        
        // Check if normalized registration number already exists
        $existingVehicle = Vehicle::where('reg_number', $normalizedRegNumber)->first();
        if ($existingVehicle) {
            return response()->json(['error' => 'This registration number is already registered in our system'], 422);
        }

        DB::beginTransaction();

        try {
            // Create vehicle
            $vehicle = Vehicle::create([
                'vehicle_name' => $request->vehicle_name,
                'vehicle_type' => $request->vehicle_type,
                'reg_number' => $normalizedRegNumber,
                'manufactured_year' => $request->manufactured_year,
                'no_of_passengers' => $request->no_of_passengers,
                'fuel_type' => $request->fuel_type,
                'driver_status' => $request->driver_status,
                'short_description' => $request->short_description,
                'long_description' => $request->long_description,
                'price_per_day' => $request->filled('price_per_day') ? $request->price_per_day : null,
                'mileage_per_day' => $request->filled('mileage_per_day') ? $request->mileage_per_day : null,
                'locations' => $request->locations,
                'vehicle_owner_id' => $request->vehicle_owner_id,
                'user_id' => $request->user_id
            ]);

            // Handle image uploads with vehicle-specific folder
            if ($request->hasFile('vehicleImage')) {
                $this->processImages($vehicle, $request->file('vehicleImage'));
            }

            DB::commit();

            // Load images for response
            $vehicle->load('images');

            return response()->json([
                'message' => 'Vehicle created successfully!',
                'vehicle' => $vehicle
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create vehicle: ' . $e->getMessage()], 500);
        }
    }

    public function updateByAuthenticatedOwner(Request $request, $id)
    {
        $vehicleOwner = VehicleOwner::where('user_id', $request->user()->id)->firstOrFail();
        $vehicle = Vehicle::with('images')
                    ->where('vehicle_owner_id', $vehicleOwner->id)
                    ->firstOrFail($id);

        $validated = $request->validate([
            'vehicle_name' => ['sometimes', 'required', 'string', 'max:255'],
            'vehicle_type' => ['sometimes', 'required', 'string', 'max:50'],
            'reg_number' => ['sometimes', 'required', 'string', 'max:24', 'regex:/^[A-Z0-9]{2,3}([\-\s]?[0-9]{3,4})?$/'],
            'manufactured_year' => ['sometimes', 'nullable', 'digits:4', 'integer', 'min:1900', 'max:'.(date('Y')+1)],
            'no_of_passengers' => ['sometimes', 'required', 'integer', 'min:1', 'max:100'],
            'fuel_type' => ['sometimes', 'required', 'string', 'max:24'],
            'driver_status' => ['sometimes', 'required', 'string', 'max:50'],
            'short_description' => ['sometimes', 'required', 'string', 'max:1000'],
            'long_description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'price_per_day' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'mileage_per_day' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'locations' => ['sometimes', 'nullable', 'array'],
            'vehicleImage' => ['sometimes', 'nullable', 'array', 'max:5'],
            'vehicleImage.*' => ['sometimes', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'removedImages' => ['sometimes', 'array'],
            'removedImages.*' => ['sometimes', 'integer', 'exists:vehicle_images,id']
        ]);

        DB::beginTransaction();

        try {
            // Handle removed images FIRST
            if ($request->has('removedImages') && !empty($request->removedImages)) {
                $removedImages = VehicleImage::where('vehicle_id', $vehicle->id)
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

            // Handle new image uploads with vehicle-specific folder
            if ($request->hasFile('vehicleImage')) {
                $this->processImages($vehicle, $request->file('vehicleImage'));
            }

            $vehicle->locations = $request->has('locations') ? $request->locations : null;

            // Normalize and validate registration number if it's being updated
            if ($request->has('reg_number')) {
                $normalizedRegNumber = $this->normalizeRegistrationNumber($request->reg_number);
                
                // Check if normalized reg_number already exists (excluding current vehicle)
                if ($normalizedRegNumber !== $vehicle->reg_number) {
                    $existingVehicle = Vehicle::where('reg_number', $normalizedRegNumber)
                        ->where('id', '!=', $id)
                        ->first();
                        
                    if ($existingVehicle) {
                        return response()->json(['error' => 'This registration number is already registered in our system'], 422);
                    }
                    
                    $vehicle->reg_number = $normalizedRegNumber;
                }
            }
        
            // Update vehicle fields
            $updateData = $request->only([
                'vehicle_name', 'vehicle_type', 'manufactured_year',
                'no_of_passengers', 'fuel_type', 'driver_status',
                'short_description', 'long_description'
            ]);

            // Handle nullable numeric fields specifically
            $updateData['price_per_day'] = $request->filled('price_per_day') ? $request->price_per_day : null;
            $updateData['mileage_per_day'] = $request->filled('mileage_per_day') ? $request->mileage_per_day : null;

            $vehicle->fill($updateData);
            $vehicle->save();

            // Reorder images to maintain consistent indexing
            $this->reorderImages($vehicle->id);

            DB::commit();

            // Refresh with images
            $vehicle->load('images');

            return response()->json([
                'message' => 'Vehicle updated successfully!',
                'vehicle' => $vehicle
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update vehicle: ' . $e->getMessage()], 500);
        }
    }

    public function deleteByAuthenticatedOwner(Request $request, $id)
    {
        $vehicleOwner = VehicleOwner::where('user_id', $request->user()->id)->firstOrFail();
        $vehicle = Vehicle::with('images')
                    ->where('vehicle_owner_id', $vehicleOwner->id)
                    ->findOrFail($id);

        DB::beginTransaction();

        try {
            // Delete the entire vehicle folder from storage
            $vehicleFolder = "vehicles/{$vehicle->id}";
            if (Storage::disk('public')->exists($vehicleFolder)) {
                Storage::disk('public')->deleteDirectory($vehicleFolder);
            }

            // Delete associated images from database
            $vehicle->images()->delete();
            
            $vehicle->delete();

            DB::commit();

            return response()->json(['message' => 'Vehicle deleted successfully!']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to delete vehicle: ' . $e->getMessage()], 500);
        }
    }

    // Process and store images for a vehicle in vehicle-specific folder
    private function processImages(Vehicle $vehicle, array $images)
    {
        $currentCount = $vehicle->images()->count();

        // Validate image count (max 5)
        if (($currentCount + count($images)) > 5) {
            throw new \Exception('Maximum 5 images allowed. Current: ' . $currentCount);
        }

        $orderIndex = $vehicle->images()->max('order_index') ?? -1;

        foreach ($images as $image) {
            $orderIndex++;

            // Store in vehicle-specific folder: vehicle/{id}/filename.jpg
            $folder = "vehicles/{$vehicle->id}";
            $filename = $this->generateUniqueFilename($image, $orderIndex);
            $path = $image->storeAs($folder, $filename, 'public');

            VehicleImage::create([
                'vehicle_id' => $vehicle->id,
                'image_path' => $path,
                'order_index' => $orderIndex,
                'alt_text' => "{$vehicle->vehicle_name} - Image " . ($orderIndex + 1)
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
    private function reorderImages($vehicleId)
    {
        $images = VehicleImage::where('vehicle_id', $vehicleId)
            ->orderBy('order_index')
            ->get();

        foreach ($images as $index => $image) {
            $image->update(['order_index' => $index]);
        }
    }

    // Normalize registration number by removing extra spaces and converting to uppercase
    private function normalizeRegistrationNumber($regNumber)
    {
        // Remove all spaces and convert to uppercase
        return strtoupper(preg_replace('/\s+/', '', $regNumber));
    }

    // Check registration number availability (for real-time validation)
    public function checkRegistrationNumber(Request $request)
    {
        $request->validate([
            'reg_number' => 'required|string|max:24',
            'exclude_id' => 'nullable|integer|exists:vehicles,id'
        ]);

        $normalizedRegNumber = $this->normalizeRegistrationNumber($request->reg_number);
        
        // Validate format against the regex
        $isValidFormat = preg_match('/^[A-Z0-9]{2,3}([\-\s]?[0-9]{3,4})?$/', $normalizedRegNumber);
        
        if (!$isValidFormat) {
            return response()->json([
                'available' => false,
                'message' => 'Invalid registration number format. Expected format: ABC123, AB-1234, BC 1234'
            ], 422);
        }

        $query = Vehicle::where('reg_number', $normalizedRegNumber);
        
        if ($request->has('exclude_id')) {
            $query->where('id', '!=', $request->exclude_id);
        }

        $exists = $query->exists();

        if ($exists) {
            return response()->json([
                'available' => false,
                'message' => 'This registration number is already registered in our system'
            ], 422);
        }

        return response()->json([
            'available' => true,
            'message' => 'Registration number is available'
        ]);
    }

    // Public methods
    public function show($id)
    {
        $vehicle = Vehicle::with(['images', 'reviews'])
                    ->withCount('reviews')
                    ->withAvg('reviews', 'rating')
                    ->findOrFail($id);

        return response()->json($vehicle);
    }

    public function index()
    {
        $vehicles = Vehicle::with('images')
                    ->withCount('reviews')
                    ->withAvg('reviews', 'rating')
                    ->get();

        return response()->json($vehicles);
    }

    public function getByOwner($ownerId)
    {
        $vehicles = Vehicle::with('images')
                        ->where('vehicle_owner_id', $ownerId)
                        ->get();

        return response()->json($vehicles);
    }

    public function getByLocation($location)
    {
        $vehicles = Vehicle::with('images')
                        ->whereJsonContains('locations', $location)
                        ->get();

        return response()->json($vehicles);
    }
}