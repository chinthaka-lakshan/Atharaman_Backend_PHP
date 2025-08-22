<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HotelOwner;
use Illuminate\Support\Facades\Auth;

class HotelOwnerController extends Controller
{
    public function index()
    {
        $hotelOwners = HotelOwner::all();
        return response()->json($hotelOwners);
    }

    public function show($id)
    {
        $hotelOwner = HotelOwner::findOrFail($id);
        return response()->json($hotelOwner);
    }

    public function store(Request $request)
    {
        $request->validate([
            'hotelOwnerName' => 'required|string|max:255',
            'hotelOwnerNic' => 'required|string|max:255',
            'businessMail' => 'required|email',
            'contactNumber' => 'required|string|max:15',
            'user_id' => 'required|exists:users,id'
        ]);

        $hotelOwner = HotelOwner::create([
            'hotelOwnerName' => $request->hotelOwnerName,
            'hotelOwnerNic' => $request->hotelOwnerNic,
            'businessMail' => $request->businessMail,
            'contactNumber' => $request->contactNumber,
            'user_id' => $request->user_id
        ]);

        return response()->json([
            'message' => 'Hotel Owner created successfully!',
            'hotelOwner' => $hotelOwner
        ]);
    }

    public function update(Request $request, $id)
    {
        $hotelOwner = HotelOwner::findOrFail($id);

        $validated = $request->validate([
            'hotelOwnerName' => 'sometimes|required|string|max:255',
            'hotelOwnerNic' => 'sometimes|required|string|max:255',
            'businessMail' => 'sometimes|required|email',
            'contactNumber' => 'sometimes|required|string|max:15',
            'user_id' => 'sometimes|required|exists:users,id'
        ]);

        $hotelOwner->fill($request->only([
            'hotelOwnerName', 'hotelOwnerNic', 'businessMail', 'contactNumber'
        ]));

        $hotelOwner->save();

        return response()->json([
            'message' => 'Hotel Owner updated successfully!',
            'hotelOwner' => $hotelOwner->fresh()
        ]);
    }

    public function destroy($id)
    {
        $hotelOwner = HotelOwner::findOrFail($id);
        $hotelOwner->delete();

        return response()->json(['message' => 'Hotel Owner deleted successfully!']);
    }
}