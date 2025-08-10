<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Hotel;
use Illuminate\Support\Facades\Auth;

class HotelController extends Controller
{
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
            'hotelImage' => 'nullable',
            'hotelImage.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Handle images (support both single and multiple file upload)
        $existingImages = json_decode($hotel->hotelImage, true) ?? [];
        $newImages = [];
        if ($request->hasFile('hotelImage')) {
            $files = $request->file('hotelImage');
            if (!is_array($files)) {
                $files = [$files];
            }
            foreach ($files as $image) {
                $path = $image->store('hotels', 'public');
                $newImages[] = $path;
            }
        }
        $hotel->hotelImage = count($newImages) > 0 ? json_encode($newImages) : json_encode($existingImages);

        if ($request->filled('hotelName')) $hotel->hotelName = $request->hotelName;
        if ($request->filled('hotelAddress')) $hotel->hotelAddress = $request->hotelAddress;
        if ($request->filled('businessMail')) $hotel->businessMail = $request->businessMail;
        if ($request->filled('contactNumber')) $hotel->contactNumber = $request->contactNumber;
        if ($request->filled('whatsappNumber')) $hotel->whatsappNumber = $request->whatsappNumber;
        if ($request->has('locations')) $hotel->locations = is_array($request->locations) ? json_encode($request->locations) : $hotel->locations;
        if ($request->filled('description')) $hotel->description = $request->description;
        if ($request->filled('hotel_owner_id')) $hotel->hotel_owner_id = $request->hotel_owner_id;

        $hotel->save();

        return response()->json([
            'message' => 'Hotel updated successfully!',
            'hotel' => $hotel
        ]);
    }

    public function destroy($id)
    {
        $hotel = Hotel::findOrFail($id);
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
        if ($hotel) {
            return response()->json($hotel);
        } else {
            return response()->json(['message' => 'Hotel not found'], 404);
        }
    }
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
        ]);
        $images = [];
        if ($request->hasFile('hotelImage')) {
            $images = [];
            foreach ($request->file('hotelImage') as $image) {
                $path = $image->store('hotels', 'public'); 
                $images[] = $path;
            }
            $hotelImagePaths = json_encode($images);
        } else {
            $hotelImagePaths = json_encode([]);
        }

        $hotel = Hotel::create([
            'hotelName' => $request->hotelName,
            'hotelAddress' => $request->hotelAddress,
            'businessMail' => $request->businessMail,
            'contactNumber' => $request->contactNumber,
            'whatsappNumber' => $request->whatsappNumber,
            'locations' => json_encode($request->locations),
            'description' => $request->description,
            'hotelImage' => $hotelImagePaths,
            'user_id' => Auth::id(),
            'hotel_owner_id' => $request->hotel_owner_id, 
        ]);

        return response()->json([
            'message' => 'Hotel created successfully!',
            'hotel' => $hotel
        ]);
    }
}
