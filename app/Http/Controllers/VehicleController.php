<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Auth;

class VehicleController extends Controller
{
    public function index()
    {
        $vehicles = Vehicle::all();
        return response()->json($vehicles);
    }

    public function show($id)
    {
        $vehicle = Vehicle::findOrFail($id);
        return response()->json($vehicle);
    }

    public function store(Request $request)
    {
        $request->validate([
            'vehicleName' => 'required|string|max:255',
            'vehicleType' => 'required|string|max:255',
            'vehicleNumber' => 'required|string|max:20',
            'pricePerDay' => 'required|string|max:255',
            'mileagePerDay' => 'required|string|max:255',
            'fuelType' => 'required|string|max:50',
            'withDriver' => 'required|string|max:3',
            'vehicleImage.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'locations' => 'nullable|array',
            'description' => 'nullable|string'
        ]);
        $images = [];
        if ($request->hasFile('vehicleImage')) {
            foreach ($request->file('vehicleImage') as $image) {
                $path = $image->store('vehicles', 'public'); // saves in storage/app/public/vehicles
                $images[] = $path;
            }
            $vehicleImagePaths = json_encode($images);
        } else {
            $vehicleImagePaths = json_encode([]);
        }

        $vehicle = Vehicle::create([
            'vehicleName' => $request->vehicleName,
            'vehicleType' => $request->vehicleType,
            'vehicleNumber' => $request->vehicleNumber,
            'pricePerDay' => $request->pricePerDay,
            'mileagePerDay' => $request->mileagePerDay,
            'fuelType' => $request->fuelType,
            'withDriver' => $request->withDriver,
            'vehicleImage' => $vehicleImagePaths,
            'locations' => json_encode($request->locations),
            'description' => $request->description,
            'user_id' => Auth::id(),
            'vehicle_owner_id' => $request->vehicle_owner_id, // Assuming this is passed in the request
        ]);

        return response()->json([
            'message' => 'Vehicle created successfully!',
            'vehicle' => $vehicle
        ]); 
    }
    public function update(Request $request, $id)
    {
        $request->validate([
            'vehicleName' => 'sometimes|required|string|max:255',
            'vehicleType' => 'sometimes|required|string|max:255',
            'vehicleNumber' => 'sometimes|required|string|max:20',
            'pricePerDay' => 'sometimes|required|numeric',
            'mileagePerDay' => 'sometimes|required|numeric',
            'fuelType' => 'sometimes|required|string|max:50',
            'withDriver' => 'sometimes|required|string|max:3',
            'vehicleImage.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'locations' => 'nullable|array',
            'description' => 'nullable|string'
        ]);

        $vehicle = Vehicle::findOrFail($id);
        $vehicle->update($request->all());

        return response()->json([
            'message' => 'Vehicle updated successfully!',
            'vehicle' => $vehicle
        ]);
    }

    public function destroy($id)
    {
        $vehicle = Vehicle::findOrFail($id);
        $vehicle->delete();

        return response()->json(['message' => 'Vehicle deleted successfully!']);
    }
}
