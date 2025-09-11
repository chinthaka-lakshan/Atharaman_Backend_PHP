<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Location;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class LocationsController extends Controller
{
    public function store(Request $request)
    {
        // Check if user is Admin
        if (Auth::user()->role !== 'Admin') {
            return response()->json(['error' => 'Unauthorized. Admin access required.'], 403);
        }

        $request->validate([
            'locationName' => 'required|string|max:255',
            'shortDescription' => 'nullable|string|max:500',
            'longDescription' => 'nullable|string|max:1000',
            'province' => 'required|string|max:100',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'locationImage.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'locationType' => 'required|string|max:100',
        ]);

        $images = [];
        if ($request->hasFile('locationImage')) {
            $images = [];
            foreach ($request->file('locationImage') as $image) {
                $path = $image->store('locations', 'public');
                $images[] = $path;
            }
        }

        $location = Location::create([
            'locationName' => $request->locationName,
            'shortDescription' => $request->shortDescription,
            'longDescription' => $request->longDescription,
            'province' => $request->province,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'locationImage' => $images,
            'locationType' => $request->locationType
        ]);

        return response()->json([
            'message' => 'Location created successfully!',
            'location' => $location
        ]);
    }

    public function update(Request $request, $id)
    {
        // Check if user is admin
        if (Auth::user()->role !== 'Admin') {
            return response()->json(['error' => 'Unauthorized. Admin access required.'], 403);
        }

        $location = Location::findorFail($id);
        $existingImages = $location->locationImage ?? [];

        $validated = $request->validate([
            'locationName' => 'sometimes|required|string|max:255',
            'shortDescription' => 'sometimes|nullable|string|max:500',
            'longDescription' => 'sometimes|nullable|string|max:1000',
            'province' => 'sometimes|required|string|max:100',
            'latitude' => 'sometimes|required|numeric',
            'longitude' => 'sometimes|required|numeric',
            'locationImage.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'locationType' => 'sometimes|required|string|max:100',
        ]);

        // Handle image updates
        if ($request->hasFile('locationImage')) {
            // Delete existing images from storage
            foreach ($existingImages as $oldImage) {
                if ($oldImage && \Storage::disk('public')->exists($oldImage)) {
                    \Storage::disk('public')->delete($oldImage);
                }
            }

            // Store new images
            $newImages = [];
            foreach ($request->file('locationImage') as $image) {
                $path = $image->store('locations', 'public');
                $newImages[] = $path;
            }
            $location->locationImage = $newImages;
        } elseif ($request->input('remove_images') === 'true') {
            // Explicit request to remove all images
            foreach ($existingImages as $oldImage) {
                if ($oldImage && \Storage::disk('public')->exists($oldImage)) {
                    \Storage::disk('public')->delete($oldImage);
                }
            }
            $location->locationImage = [];
        }

        // Update other fields
        $location->fill($request->only([
            'locationName', 'shortDescription', 'longDescription',
            'province', 'latitude', 'longitude', 'locationType'
        ]));

        $location->save();

        return response()->json([
            'message' => 'Location updated successfully!',
            'location' => $location->fresh()
        ]);
    }

    public function destroy($id)
    {
        // Check if user is admin
        if (Auth::user()->role !== 'Admin') {
            return response()->json(['error' => 'Unauthorized. Admin access required.'], 403);
        }

        $location = Location::findOrFail($id);

        // Delete associated images
        $images = $location->locationImage ?? [];
        foreach ($images as $image) {
            if ($image && \Storage::disk('public')->exists($image)) {
                \Storage::disk('public')->delete($image);
            }
        }

        $location->delete();

        return response()->json(['message' => 'Location deleted successfully!']);
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

    public function show($id)
    {
        $location = Location::findOrFail($id);
        return response()->json($location);
    }

    public function index()
    {
        $locations = Location::all();
        return response()->json($locations);
    }
}