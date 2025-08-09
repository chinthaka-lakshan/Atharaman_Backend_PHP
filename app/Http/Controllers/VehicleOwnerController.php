<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VehicleOwner;
use Illuminate\Support\Facades\Auth;

class VehicleOwnerController extends Controller
{
    public function index()
    {
        $vehicleOwners = VehicleOwner::all();
        return response()->json($vehicleOwners);
    }
    public function show($id)
    {
        $vehicleOwner = VehicleOwner::findOrFail($id);
        return response()->json($vehicleOwner);
    }

    public function store(Request $request)
    {
        $request->validate([
            'vehicleOwnerName' => 'required|string|max:255',
            'vehicleOwnerNic' => 'required|string|max:255',
            'businessMail' => 'required|email',
            'personalNumber' => 'required|string|max:15',
            'whatsappNumber' => 'nullable|string|max:15',
            'locations' => 'nullable|array',
            'description' => 'nullable|string'
        ]);

        $vehicleOwner = VehicleOwner::create([
            'vehicleOwnerName' => $request->vehicleOwnerName,
            'vehicleOwnerNic' => $request->vehicleOwnerNic,
            'businessMail' => $request->businessMail,
            'personalNumber' => $request->personalNumber,
            'whatsappNumber' => $request->whatsappNumber,
            'locations' => json_encode($request->locations),
            'description' => $request->description,
            'user_id' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Vehicle Owner created successfully!',
            'vehicleOwner' => $vehicleOwner
        ]);
    }


    public function destroy($id)
    {
        $vehicleOwner = VehicleOwner::findOrFail($id);
        $vehicleOwner->delete();

        return response()->json(['message' => 'Vehicle Owner deleted successfully!']);
    }

    public function getByLocation($location)
    {
        $vehicleOwners = VehicleOwner::where('locations', 'LIKE', "%{$location}%")->get();
        return response()->json($vehicleOwners);
    }
}
