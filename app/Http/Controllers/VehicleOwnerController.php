<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VehicleOwner;
use App\Models\Role;
use App\Models\User;
use App\Models\RoleRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class VehicleOwnerController extends Controller
{
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

        // Get the shop owner role
        $vehicleOwnerRole = Role::where('name', 'vehicle_owner')->first();
        
        if (!$vehicleOwnerRole) {
            return response()->json(['error' => 'Vehicle owner role not found'], 404);
        }

        // Get the user
        $user = User::findOrFail($request->user_id);

        try {
            // Use DB transaction for data consistency
            $vehicleOwner = DB::transaction(function () use ($request, $user, $vehicleOwnerRole) {
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

                // Attach vehicle owner role to user if not already attached
                // Use syncWithoutDetaching to avoid duplicates
                $user->roles()->syncWithoutDetaching([$vehicleOwnerRole->id]);

                // Create or update role request status to 'accepted'
                RoleRequest::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'role_id' => $vehicleOwnerRole->id,
                    ],
                    [
                        'status' => 'accepted',
                        'extra_data' => [
                            'vehicleOwnerName' => $request->vehicleOwnerName,
                            'vehicleOwnerNic' => $request->vehicleOwnerNic,
                            'businessMail' => $request->businessMail,
                            'personalNumber' => $request->personalNumber,
                            'whatsappNumber' => $request->whatsappNumber,
                            'locations' => $request->locations,
                            'description' => $request->description
                        ]
                    ]
                );

                return $vehicleOwner;
            });

            return response()->json([
                'message' => 'Vehicle Owner created successfully!',
                'vehicleOwner' => $vehicleOwner
            ]);

        } catch (\Exception $e) {
            \Log::error('Vehicle owner creation failed: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to create vehicle owner: ' . $e->getMessage()
            ], 500);
        }
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

    public function destroy($id)
    {
        try {
            $vehicleOwner = VehicleOwner::findOrFail($id);
            $userId = $vehicleOwner->user_id;

            DB::transaction(function () use ($vehicleOwner, $userId) {
                // Get the vehicle owner role
                $vehicleOwnerRole = Role::where('name', 'vehicle_owner')->first();
                
                if ($vehicleOwnerRole) {
                    // Remove the role from the user
                    DB::table('role_user')
                        ->where('user_id', $userId)
                        ->where('role_id', $vehicleOwnerRole->id)
                        ->delete();
                }

                $vehicleOwner->delete();
            });

            return response()->json(['message' => 'Vehicle owner deleted successfully!']);

        } catch (\Exception $e) {
            \Log::error('Vehicle owner deletion failed: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to delete vehicle owner: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getByLocation($location)
    {
        $vehicleOwners = VehicleOwner::where('locations', 'LIKE', "%{$location}%")->get();
        return response()->json($vehicleOwners);
    }
}