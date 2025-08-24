<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Hotel;
use Illuminate\Support\Facades\Auth;

class HotelController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'hotelName' => 'required|string|max:255',
            'hotelAddress' => 'required|string|max:255',
            'businessMail' => 'required|email',
            'contactNumber' => 'required|string|max:15',
            'whatsappNumber' => 'nullable|string|max:15',
            'locations' => 'nullable|array',
            'description' => 'nullable|string',
            'hotelImage.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'hotel_owner_id' => 'required|exists:hotel_owners,id',
            'user_id' => 'required|exists:users,id'
        ]);

        $images = [];
        if ($request->hasFile('hotelImage')) {
            $images = [];
            foreach ($request->file('hotelImage') as $image) {
                $path = $image->store('hotels', 'public'); 
                $images[] = $path;
            }
        }

        $hotel = Hotel::create([
            'hotelName' => $request->hotelName,
            'hotelAddress' => $request->hotelAddress,
            'businessMail' => $request->businessMail,
            'contactNumber' => $request->contactNumber,
            'whatsappNumber' => $request->whatsappNumber,
            'locations' => $request->locations,
            'description' => $request->description,
            'hotelImage' => $images,
            'user_id' => $request->user_id,
            'hotel_owner_id' => $request->hotel_owner_id
        ]);

        return response()->json([
            'message' => 'Hotel created successfully!',
            'hotel' => $hotel
        ]);
    }

    public function update(Request $request, $id)
    {
        $hotel = Hotel::findOrFail($id);

        $validated = $request->validate([
            'hotelName' => 'sometimes|required|string|max:255',
            'hotelAddress' => 'sometimes|required|string|max:255',
            'businessMail' => 'sometimes|required|email',
            'contactNumber' => 'sometimes|required|string|max:15',
            'whatsappNumber' => 'nullable|string|max:15',
            'locations' => 'nullable|array',
            'description' => 'nullable|string',
            'hotelImage.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'user_id' => 'sometimes|required|exists:users,id',
            'hotel_owner_id' => 'sometimes|required|exists:hotel_owners,id'
        ]);

        // Handle image updates
        if ($request->hasFile('hotelImage')) {
            // Delete existing images from storage
            foreach ($existingImages as $oldImage) {
                \Storage::disk('public')->delete($oldImage);
            }
            // Store new images
            $newImages = [];
            foreach ($request->file('hotelImage') as $image) {
                $path = $image->store('hotels', 'public');
                $newImages[] = $path;
            }
            $hotel->hotelImage = $newImages;
        } elseif ($request->input('remove_images') === 'true') {
            // Explicit request to remove all images
            foreach ($existingImages as $oldImage) {
                \Storage::disk('public')->delete($oldImage);
            }
            $hotel->hotelImage = [];
        }

        // Update other fields
        $hotel->fill($request->only([
            'hotelName', 'hotelAddress', 'businessMail',
            'contactNumber', 'whatsappNumber', 'description'
        ]));

        if ($request->has('locations')) {
            $hotel->locations = $request->locations;
        }

        $hotel->save();

        return response()->json([
            'message' => 'Hotel updated successfully!',
            'hotel' => $hotel->fresh()
        ]);
    }

    public function destroy($id)
    {
        $hotel = Hotel::findOrFail($id);

        // Delete associated images
        $images = $hotel->hotelImage ?? [];
        foreach ($images as $image) {
            \Storage::disk('public')->delete($image);
        }

        $hotel->delete();

        return response()->json(['message' => 'Hotel deleted successfully!']);
    }

    public function index()
    {
        $hotels = Hotel::all();
        return response()->json($hotels);
    }

    public function show($id)
    {
        $hotel = Hotel::find($id);
        return response()->json($hotel);
    }

    public function getByLocation($location)
    {
        $hotels = Hotel::whereJsonContains('locations', $location)->get();
        return response()->json($hotels);
    }
}