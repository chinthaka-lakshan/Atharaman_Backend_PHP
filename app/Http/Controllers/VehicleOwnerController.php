<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\VehicleOwner;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class VehicleOwnerController extends Controller
{
    // Admin side methods
    public function store(Request $request)
    {
        $request->validate([
            'vehicle_owner_name' => ['required', 'string', 'max:255'],
            'vehicle_owner_nic' => ['required', 'string', 'max:24'],
            'vehicle_owner_dob' => ['required', 'date'],
            'vehicle_owner_address' => ['required', 'string', 'max:500'],
            'business_mail' => ['required', 'email'],
            'contact_number' => ['required', 'string', 'max:15'],
            'whatsapp_number' => ['nullable', 'string', 'max:15'],
            'locations' => ['nullable', 'array'],
            'user_id' => ['required', 'exists:users,id']
        ]);

        // Check if NIC already exists in vehicle_owners table
        $existingVehicleOwner = VehicleOwner::where('vehicle_owner_nic', $request->vehicle_owner_nic)->first();
        if ($existingVehicleOwner) {
            return response()->json(['error' => 'This NIC is already registered as a vehicle owner in our system'], 422);
        }

        // Get the vehicle owner role
        $vehicleOwnerRole = Role::where('name', 'vehicle_owner')->first();
        
        if (!$vehicleOwnerRole) {
            return response()->json(['error' => 'Vehicle owner role not found'], 404);
        }

        // Get the user
        $user = User::findOrFail($request->user_id);

        // Start transaction for data consistency
        DB::beginTransaction();

        try {
            // Create vehicle owner
            $vehicleOwner = VehicleOwner::create([
                'vehicle_owner_name' => $request->vehicle_owner_name,
                'vehicle_owner_nic' => $request->vehicle_owner_nic,
                'vehicle_owner_dob' => $request->vehicle_owner_dob,
                'vehicle_owner_address' => $request->vehicle_owner_address,
                'business_mail' => $request->business_mail,
                'contact_number' => $request->contact_number,
                'whatsapp_number' => $request->whatsapp_number,
                'locations' => $request->locations,
                'user_id' => $request->user_id
            ]);

            // Attach vehicle owner role to user if not already attached
            // Use syncWithoutDetaching to avoid duplicates
            $user->roles()->syncWithoutDetaching([$vehicleOwnerRole->id]);

            DB::commit();

            return response()->json([
                'message' => 'Vehicle Owner created successfully!',
                'vehicleOwner' => $vehicleOwner
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create vehicle owner: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $vehicleOwner = VehicleOwner::findOrFail($id);

        $validated = $request->validate([
            'vehicle_owner_name' => ['sometimes', 'required', 'string', 'max:255'],
            'vehicle_owner_nic' => ['sometimes', 'required', 'string', 'max:24'],
            'vehicle_owner_dob' => ['sometimes', 'required', 'date'],
            'vehicle_owner_address' => ['sometimes', 'required', 'string', 'max:500'],
            'business_mail' => ['sometimes', 'required', 'email'],
            'contact_number' => ['sometimes', 'required', 'string', 'max:15'],
            'whatsapp_number' => ['sometimes', 'nullable', 'string', 'max:15'],
            'locations' => ['sometimes', 'nullable', 'array']
        ]);

        // Check if NIC already exists in vehicle_owners table (excluding current vehicle owner)
        if ($request->has('vehicle_owner_nic') && $request->vehicle_owner_nic !== $vehicleOwner->vehicle_owner_nic) {
            $existingVehicleOwner = VehicleOwner::where('vehicle_owner_nic', $request->vehicle_owner_nic)
                ->where('id', '!=', $id)
                ->first();
            if ($existingVehicleOwner) {
                return response()->json(['error' => 'This NIC is already registered as a vehicle owner in our system'], 422);
            }
        }

        DB::beginTransaction();

        try {
            $vehicleOwner->locations = $request->has('locations') ? $request->locations : null;

            // Update vehicle owner fields
            $vehicleOwner->fill($request->only([
                'vehicle_owner_name', 'vehicle_owner_nic', 'vehicle_owner_dob', 'vehicle_owner_address',
                'business_mail', 'contact_number', 'whatsapp_number'
            ]));

            $vehicleOwner->save();

            DB::commit();

            return response()->json([
                'message' => 'Vehicle Owner updated successfully!',
                'vehicleOwner' => $vehicleOwner
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update vehicle owner: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $vehicleOwner = VehicleOwner::findOrFail($id);
        $userId = $vehicleOwner->user_id;

        DB::beginTransaction();

        try {
            $vehicleOwnerRole = Role::where('name', 'vehicle_owner')->first();
                
            if ($vehicleOwnerRole) {
                // Remove the role from the user
                DB::table('role_user')
                    ->where('user_id', $userId)
                    ->where('role_id', $vehicleOwnerRole->id)
                    ->delete();

                // DELETE the role requests record
                DB::table('role_requests')
                    ->where('user_id', $vehicleOwner->user_id)
                    ->where('role_id', $vehicleOwnerRole->id)
                    ->delete();
            }

            $vehicleOwner->delete();

            DB::commit();

            return response()->json(['message' => 'Vehicle owner deleted successfully!']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to delete vehicle owner: ' . $e->getMessage()], 500);
        }
    }

    // User side methods
    public function getByAuthenticatedUser(Request $request)
    {
        $vehicleOwner = VehicleOwner::where('user_id', $request->user()->id)->first();
        
        if (!$vehicleOwner) {
            return response()->json(['error' => 'Vehicle owner not found'], 404);
        }
        
        return response()->json($vehicleOwner);
    }

    public function updateByAuthenticatedUser(Request $request)
    {
        $vehicleOwner = VehicleOwner::where('user_id', $request->user()->id)->firstOrFail();

        if (!$vehicleOwner) {
            return response()->json(['error' => 'You do not have a vehicle owner profile.'], 403);
        }

        $validated = $request->validate([
            'vehicle_owner_name' => ['sometimes', 'required', 'string', 'max:255'],
            'vehicle_owner_nic' => ['sometimes', 'required', 'string', 'max:24'],
            'vehicle_owner_dob' => ['sometimes', 'required', 'date'],
            'vehicle_owner_address' => ['sometimes', 'required', 'string', 'max:500'],
            'business_mail' => ['sometimes', 'required', 'email'],
            'contact_number' => ['sometimes', 'required', 'string', 'max:15'],
            'whatsapp_number' => ['sometimes', 'nullable', 'string', 'max:15'],
            'locations' => ['sometimes', 'nullable', 'array']
        ]);

        DB::beginTransaction();

        try {
            $vehicleOwner->locations = $request->has('locations') ? $request->locations : null;

            // Update vehicle owner fields
            $vehicleOwner->fill($request->only([
                'vehicle_owner_name', 'vehicle_owner_nic', 'vehicle_owner_dob', 'vehicle_owner_address',
                'business_mail', 'contact_number', 'whatsapp_number'
            ]));

            $vehicleOwner->save();

            DB::commit();

            return response()->json([
                'message' => 'Vehicle Owner updated successfully!',
                'vehicleOwner' => $vehicleOwner
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update vehicle owner: ' . $e->getMessage()], 500);
        }
    }

    public function deleteByAuthenticatedUser(Request $request)
    {
        $vehicleOwner = VehicleOwner::where('user_id', $request->user()->id)->firstOrFail();
        $userId = $vehicleOwner->user_id;

        DB::beginTransaction();
        
        try {
            $vehicleOwnerRole = Role::where('name', 'vehicle_owner')->first();
                
            if ($vehicleOwnerRole) {
                // Remove the role from the user
                DB::table('role_user')
                    ->where('user_id', $userId)
                    ->where('role_id', $vehicleOwnerRole->id)
                    ->delete();
                
                // DELETE the role requests record
                DB::table('role_requests')
                    ->where('user_id', $vehicleOwner->user_id)
                    ->where('role_id', $vehicleOwnerRole->id)
                    ->delete();
            }

            $vehicleOwner->delete();

            DB::commit();

            return response()->json(['message' => 'Vehicle owner deleted successfully!']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to delete vehicle owner: ' . $e->getMessage()], 500);
        }
    }

    // Public methods
    public function show($id)
    {
        $vehicleOwner = VehicleOwner::findOrFail($id);
        return response()->json($vehicleOwner);
    }

    public function index()
    {
        $vehicleOwners = VehicleOwner::get();
        return response()->json($vehicleOwners);
    }

    public function getByLocation($location)
    {
        $vehicleOwners = VehicleOwner::where('locations', 'LIKE', "%{$location}%")->get();
        return response()->json($vehicleOwners);
    }
}