<?php

namespace App\Http\Controllers;
use App\Models\Location;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class LocationsController extends Controller
{
    public function index()
    {
        return response()->json([
            'locations' => Location::all()
        ]);
    }

    public function show($id)
    {
        $location = Location::find($id);

        if (!$location) {
            return response()->json(['message' => 'Location not found'], 404);
        }

        return response()->json([
            'location' => $location
        ]);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'locationName' => 'required|string|max:255',
            'shortDescription' => 'nullable|string|max:500',
            'longDescription' => 'nullable|string|max:1000',
            'province' => 'required|string|max:100',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'locationImage.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);
        $images = [];
        if ($request->hasFile('locationImage')) {
            foreach ($request->file('locationImage') as $image) {
                $path = $image->store('locations', 'public'); // saves in storage/app/public/locations
                $images[] = $path;
            }
            $validatedData['locationImage'] = json_encode($images);
        } else {
            $validatedData['locationImage'] = json_encode([]);
        }

        $location = Location::create([
            'locationName' => $validatedData['locationName'],
            'shortDescription' => $validatedData['shortDescription'],
            'longDescription' => $validatedData['longDescription'],
            'province' => $validatedData['province'],
            'latitude' => $validatedData['latitude'],
            'longitude' => $validatedData['longitude'],
            'locationImage' => $validatedData['locationImage'],
        ]);

        return response()->json([
            'message' => 'Location created successfully!',
            'location' => $location
        ]);
    }

    public function update(Request $request, $id)
    {
        $location = Location::find($id);

        if (!$location) {
            return response()->json(['message' => 'Location not found'], 404);
        }

        $validatedData = $request->validate([
            'locationName' => 'required|string|max:255',
            'shortDescription' => 'nullable|string|max:500',
            'longDescription' => 'nullable|string|max:1000',
            'province' => 'required|string|max:100',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'locationImage.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $images = [];
        if ($request->hasFile('locationImage')) {
            foreach ($request->file('locationImage') as $image) {
                $path = $image->store('locations', 'public');
                $images[] = $path;
            }
            $validatedData['locationImage'] = json_encode($images);
        } else {
            $validatedData['locationImage'] = json_encode([]);
        }

        $location->update($validatedData);

        return response()->json([
            'message' => 'Location updated successfully!',
            'location' => $location
        ]);
    }

    public function destroy($id)
    {
        $location = Location::find($id);

        if (!$location) {
            return response()->json(['message' => 'Location not found'], 404);
        }

        $location->delete();

        return response()->json([
            'message' => 'Location deleted successfully!'
        ]);
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
    
}
