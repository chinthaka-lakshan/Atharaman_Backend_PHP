<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Vehicle;
use App\Models\VehicleOwner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class VehicleController extends Controller
{
    // Admin side methods
    public function store(Request $request)
    {
        $request->validate([
            'vehicleName' => 'required|string|max:255',
            'vehicleType' => 'required|string|max:255',
            'vehicleNumber' => 'required|string|max:20',
            'pricePerDay' => 'required|string|max:255',
            'mileagePerDay' => 'required|string|max:255',
            'fuelType' => 'required|string|max:50',
            'withDriver' => 'required|string|max:50',
            'vehicleImage.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'locations' => 'nullable|array',
            'description' => 'nullable|string',
            'vehicle_owner_id' => 'required|exists:vehicle_owners,id',
            'user_id' => 'required|exists:users,id'
        ]);

        $images = [];
        if ($request->hasFile('vehicleImage')) {
            $images = [];
            foreach ($request->file('vehicleImage') as $image) {
                $path = $image->store('vehicles', 'public');
                $images[] = $path;
            }
        }

        $vehicle = Vehicle::create([
            'vehicleName' => $request->vehicleName,
            'vehicleType' => $request->vehicleType,
            'vehicleNumber' => $request->vehicleNumber,
            'pricePerDay' => $request->pricePerDay,
            'mileagePerDay' => $request->mileagePerDay,
            'fuelType' => $request->fuelType,
            'withDriver' => $request->withDriver,
            'vehicleImage' => $images,
            'locations' => $request->locations,
            'description' => $request->description,
            'user_id' => $request->user_id,
            'vehicle_owner_id' => $request->vehicle_owner_id
        ]);

        return response()->json([
            'message' => 'Vehicle created successfully!',
            'vehicle' => $vehicle
        ]); 
    }

    public function update(Request $request, $id)
    {
        $vehicle = Vehicle::findOrFail($id);
        $existingImages = $vehicle->vehicleImage ?? [];

        $validated = $request->validate([
            'vehicleName' => 'sometimes|required|string|max:255',
            'vehicleType' => 'sometimes|required|string|max:255',
            'vehicleNumber' => 'sometimes|required|string|max:20',
            'pricePerDay' => 'sometimes|required|numeric',
            'mileagePerDay' => 'sometimes|required|numeric',
            'fuelType' => 'sometimes|required|string|max:50',
            'withDriver' => 'sometimes|required|string|max:50',
            'vehicleImage.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'locations' => 'nullable|array',
            'description' => 'nullable|string',
            'user_id' => 'sometimes|required|exists:users,id',
            'vehicle_owner_id' => 'sometimes|required|exists:vehicle_owners,id'
        ]);

        // Handle image updates
        if ($request->hasFile('vehicleImage')) {
            // Delete existing images from storage
            foreach ($existingImages as $oldImage) {
                \Storage::disk('public')->delete($oldImage);
            }
            // Store new images
            $newImages = [];
            foreach ($request->file('vehicleImage') as $image) {
                $path = $image->store('vehicles', 'public');
                $newImages[] = $path;
            }
            $vehicle->vehicleImage = $newImages;
        } elseif ($request->input('remove_images') === 'true') {
            // Explicit request to remove all images
            foreach ($existingImages as $oldImage) {
                \Storage::disk('public')->delete($oldImage);
            }
            $vehicle->vehicleImage = [];
        }

        // Update other fields
        $vehicle->fill($request->only([
            'vehicleName', 'vehicleType', 'vehicleNumber',
            'pricePerDay', 'mileagePerDay', 'fuelType',
            'withDriver', 'description'
        ]));

        if ($request->has('locations')) {
            $vehicle->locations = $request->locations;
        }

        $vehicle->save();

        return response()->json([
            'message' => 'Vehicle updated successfully!',
            'vehicle' => $vehicle->fresh()
        ]);
    }

    public function destroy($id)
    {
        $vehicle = Vehicle::findOrFail($id);

        // Delete associated images
        $images = $vehicle->vehicleImage ?? [];
        foreach ($images as $image) {
            \Storage::disk('public')->delete($image);
        }

        $vehicle->delete();

        return response()->json(['message' => 'Vehicle deleted successfully!']);
    }

    // User side methods
    public function getByAuthenticatedOwner(Request $request)
    {
        $vehicleOwner = VehicleOwner::where('user_id', $request->user()->id)->firstOrFail();
        $vehicles = Vehicle::where('vehicle_owner_id', $vehicleOwner->id)->get();
        
        return response()->json($vehicles);
    }

    public function storeByAuthenticatedOwner(Request $request)
    {
        $vehicleOwner = VehicleOwner::where('user_id', $request->user()->id)->firstOrFail();

        $request->validate([
            'vehicleName' => 'sometimes|required|string|max:255',
            'vehicleType' => 'sometimes|required|string|max:255',
            'vehicleNumber' => 'sometimes|required|string|max:20',
            'pricePerDay' => 'sometimes|required|numeric',
            'mileagePerDay' => 'sometimes|required|numeric',
            'fuelType' => 'sometimes|required|string|max:50',
            'withDriver' => 'sometimes|required|string|max:50',
            'vehicleImage.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'locations' => 'nullable|array',
            'description' => 'nullable|string',
        ]);

        $images = [];
        if ($request->hasFile('vehicleImage')) {
            foreach ($request->file('vehicleImage') as $image) {
                $path = $image->store('vehicles', 'public'); 
                $images[] = $path;
            }
        }

        $vehicle = Vehicle::create([
            'vehicleName' => $request->vehicleName,
            'vehicleType' => $request->vehicleType,
            'vehicleNumber' => $request->vehicleNumber,
            'pricePerDay' => $request->pricePerDay,
            'mileagePerDay' => $request->mileagePerDay,
            'fuelType' => $request->fuelType,
            'withDriver' => $request->withDriver,
            'vehicleImage' => $images,
            'locations' => $request->locations,
            'description' => $request->description,
            'user_id' => $request->user()->id,
            'vehicle_owner_id' => $vehicleOwner->id
        ]);

        return response()->json([
            'message' => 'Vehicle created successfully!',
            'vehicle' => $vehicle
        ]);
    }

    public function updateByAuthenticatedOwner(Request $request, $id)
    {
        $vehicleOwner = VehicleOwner::where('user_id', $request->user()->id)->firstOrFail();
        $vehicle = Vehicle::where('id', $id)
                    ->where('vehicle_owner_id', $vehicleOwner->id)
                    ->firstOrFail();
        
        $existingImages = $vehicle->vehicleImage ?? [];

        $validated = $request->validate([
            'vehicleName' => 'sometimes|required|string|max:255',
            'vehicleType' => 'sometimes|required|string|max:255',
            'vehicleNumber' => 'sometimes|required|string|max:20',
            'pricePerDay' => 'sometimes|required|numeric',
            'mileagePerDay' => 'sometimes|required|numeric',
            'fuelType' => 'sometimes|required|string|max:50',
            'withDriver' => 'sometimes|required|string|max:50',
            'vehicleImage.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'locations' => 'nullable|array',
            'description' => 'nullable|string',
        ]);

        // Handle image updates
        if ($request->hasFile('vehicleImage')) {
            // Delete existing images from storage
            foreach ($existingImages as $oldImage) {
                Storage::disk('public')->delete($oldImage);
            }
            // Store new images
            $newImages = [];
            foreach ($request->file('vehicleImage') as $image) {
                $path = $image->store('vehicles', 'public');
                $newImages[] = $path;
            }
            $vehicle->vehicleImage = $newImages;
        } elseif ($request->input('remove_images') === 'true') {
            // Explicit request to remove all images
            foreach ($existingImages as $oldImage) {
                Storage::disk('public')->delete($oldImage);
            }
            $vehicle->vehicleImage = [];
        }
        
        // Update other fields
        $vehicle->fill($request->only([
            'vehicleName', 'vehicleType', 'vehicleNumber',
            'pricePerDay', 'mileagePerDay', 'fuelType',
            'withDriver', 'description'
        ]));

        if ($request->has('locations')) {
            $vehicle->locations = $request->locations;
        }

        $vehicle->save();

        return response()->json([
            'message' => 'Vehicle updated successfully!',
            'vehicle' => $vehicle->fresh()
        ]);
    }

    public function deleteByAuthenticatedOwner(Request $request, $id)
    {
        $vehicleOwner = VehicleOwner::where('user_id', $request->user()->id)->firstOrFail();
        $vehicle = Vehicle::where('id', $id)
                    ->where('vehicle_owner_id', $vehicleOwner->id)
                    ->firstOrFail();
        
        // Delete associated images
        $images = $vehicle->vehicleImage ?? [];
        foreach ($images as $image) {
            \Storage::disk('public')->delete($image);
        }

        $vehicle->delete();

        return response()->json(['message' => 'Vehicle deleted successfully!']);
    }

    // Public methods
    public function show($id)
    {
        $vehicle = Vehicle::findOrFail($id);
        return response()->json($vehicle);
    }

    public function index()
    {
        $vehicles = Vehicle::all();
        return response()->json($vehicles);
    }

    public function getByOwner($ownerId)
    {
        $vehicles = Vehicle::where('vehicle_owner_id', $ownerId)->get();
        return response()->json($vehicles);
    }

    public function getByLocation($location)
    {
        $vehicles = Vehicle::whereJsonContains('locations', $location)->get();
        return response()->json($vehicles);
    }
}