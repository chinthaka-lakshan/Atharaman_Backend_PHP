<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Hotel;
use Illuminate\Support\Facades\Auth;

class HotelController extends Controller
{
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
