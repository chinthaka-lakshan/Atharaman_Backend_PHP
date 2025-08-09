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
            'contactNumber' => 'required|string|max:15',
        ]);

        $vehicleOwner = VehicleOwner::create([
            'vehicleOwnerName' => $request->vehicleOwnerName,
            'vehicleOwnerNic' => $request->vehicleOwnerNic,
            'businessMail' => $request->businessMail,
            'contactNumber' => $request->contactNumber,
            'user_id' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Vehicle Owner created successfully!',
            'vehicleOwner' => $vehicleOwner
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'vehicleOwnerName' => 'sometimes|required|string|max:255',
            'vehicleOwnerNic' => 'sometimes|required|string|max:255',
            'businessMail' => 'sometimes|required|email',
            'contactNumber' => 'sometimes|required|string|max:15',
        ]);

        $vehicleOwner = VehicleOwner::findOrFail($id);
        $vehicleOwner->update($request->all());

        return response()->json([
            'message' => 'Vehicle Owner updated successfully!',
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
