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
            'description' => 'nullable|string',
            'user_id' => 'required|exists:users,id'
        ]);

        $vehicleOwner = VehicleOwner::create([
            'vehicleOwnerName' => $request->vehicleOwnerName,
            'vehicleOwnerNic' => $request->vehicleOwnerNic,
            'businessMail' => $request->businessMail,
            'personalNumber' => $request->personalNumber,
            'whatsappNumber' => $request->whatsappNumber,
            'locations' => $request->locations,
            'description' => $request->description,
            'user_id' => $request->user_id
        ]);

        return response()->json([
            'message' => 'Vehicle Owner created successfully!',
            'vehicleOwner' => $vehicleOwner
        ]);
    }

    public function update(Request $request, $id)
    {
        $vehicleOwner = VehicleOwner::findOrFail($id);

        $validated = $request->validate([
            'vehicleOwnerName' => 'sometimes|required|string|max:255',
            'vehicleOwnerNic' => 'sometimes|required|string|max:255',
            'businessMail' => 'sometimes|required|email',
            'personalNumber' => 'sometimes|required|string|max:15',
            'whatsappNumber' => 'nullable|string|max:15',
            'locations' => 'nullable|array',
            'description' => 'nullable|string',
            'user_id' => 'sometimes|required|exists:users,id'
        ]);

        $vehicleOwner->fill($request->only([
            'vehicleOwnerName', 'vehicleOwnerNic', 'businessMail',
            'personalNumber', 'whatsappNumber', 'description'
        ]));

        if ($request->has('locations')) {
            $vehicleOwner->locations = $request->locations;
        }

        $vehicleOwner->save();

        return response()->json([
            'message' => 'Vehicle Owner updated successfully!',
            'vehicleOwner' => $vehicleOwner->fresh()
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