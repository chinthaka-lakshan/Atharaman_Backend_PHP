<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HotelOwner;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class HotelOwnerController extends Controller
{
    // Admin side methods
    public function store(Request $request)
    {
        $request->validate([
            'hotel_owner_name' => ['required', 'string', 'max:255'],
            'hotel_owner_nic' => ['required', 'string', 'max:24'],
            'hotel_owner_dob' => ['required', 'date'],
            'hotel_owner_address' => ['required', 'string', 'max:500'],
            'business_mail' => ['required', 'email'],
            'contact_number' => ['required', 'string', 'max:15'],
            'whatsapp_number' => ['nullable', 'string', 'max:15'],
            'user_id' => ['required', 'exists:users,id']
        ]);

        // Check if NIC already exists in hotel_owners table
        $existingHotelOwner = HotelOwner::where('hotel_owner_nic', $request->hotel_owner_nic)->first();
        if ($existingHotelOwner) {
            return response()->json(['error' => 'This NIC is already registered as a hotel owner in our system'], 422);
        }

        // Get the hotel owner role
        $hotelOwnerRole = Role::where('name', 'hotel_owner')->first();
        
        if (!$hotelOwnerRole) {
            return response()->json(['error' => 'Hotel owner role not found'], 404);
        }

        // Get the user
        $user = User::findOrFail($request->user_id);

        // Start transaction for data consistency
        DB::beginTransaction();

        try {
            // Create hotel owner
            $hotelOwner = HotelOwner::create([
                'hotel_owner_name' => $request->hotel_owner_name,
                'hotel_owner_nic' => $request->hotel_owner_nic,
                'hotel_owner_dob' => $request->hotel_owner_dob,
                'hotel_owner_address' => $request->hotel_owner_address,
                'business_mail' => $request->business_mail,
                'contact_number' => $request->contact_number,
                'whatsapp_number' => $request->whatsapp_number,
                'user_id' => $request->user_id
            ]);

            // Attach hotel owner role to user if not already attached
            // Use syncWithoutDetaching to avoid duplicates
            $user->roles()->syncWithoutDetaching([$hotelOwnerRole->id]);

            DB::commit();

            return response()->json([
                'message' => 'Hotel Owner created successfully!',
                'hotelOwner' => $hotelOwner
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create hotel owner: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $hotelOwner = HotelOwner::findOrFail($id);

        $validated = $request->validate([
            'hotel_owner_name' => ['sometimes', 'required', 'string', 'max:255'],
            'hotel_owner_nic' => ['sometimes', 'required', 'string', 'max:24'],
            'hotel_owner_dob' => ['sometimes', 'required', 'date'],
            'hotel_owner_address' => ['sometimes', 'required', 'string', 'max:500'],
            'business_mail' => ['sometimes', 'required', 'email'],
            'contact_number' => ['sometimes', 'required', 'string', 'max:15'],
            'whatsapp_number' => ['sometimes', 'nullable', 'string', 'max:15']
        ]);

        // Check if NIC already exists in hotel_owners table (excluding current hotel owner)
        if ($request->has('hotel_owner_nic') && $request->hotel_owner_nic !== $hotelOwner->hotel_owner_nic) {
            $existingHotelOwner = HotelOwner::where('hotel_owner_nic', $request->hotel_owner_nic)
                ->where('id', '!=', $id)
                ->first();
            if ($existingHotelOwner) {
                return response()->json(['error' => 'This NIC is already registered as a hotel owner in our system'], 422);
            }
        }

        DB::beginTransaction();

        try {
            // Update hotel owner fields
            $hotelOwner->fill($request->only([
                'hotel_owner_name', 'hotel_owner_nic', 'hotel_owner_dob', 'hotel_owner_address',
                'business_mail', 'contact_number', 'whatsapp_number'
            ]));

            $hotelOwner->save();

            DB::commit();

            return response()->json([
                'message' => 'Hotel Owner updated successfully!',
                'hotelOwner' => $hotelOwner
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update hotel owner: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $hotelOwner = HotelOwner::findOrFail($id);
        $userId = $hotelOwner->user_id;

        DB::beginTransaction();

        try {
            $hotelOwnerRole = Role::where('name', 'hotel_owner')->first();
                
            if ($hotelOwnerRole) {
                // Remove the role from the user
                DB::table('role_user')
                    ->where('user_id', $userId)
                    ->where('role_id', $hotelOwnerRole->id)
                    ->delete();

                // DELETE the role requests record
                DB::table('role_requests')
                    ->where('user_id', $hotelOwner->user_id)
                    ->where('role_id', $hotelOwnerRole->id)
                    ->delete();
            }

            $hotelOwner->delete();

            DB::commit();

            return response()->json(['message' => 'Hotel owner deleted successfully!']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to delete hotel owner: ' . $e->getMessage()], 500);
        }
    }

    // User side methods
    public function getByAuthenticatedUser(Request $request)
    {
        $hotelOwner = HotelOwner::where('user_id', $request->user()->id)->first();
        
        if (!$hotelOwner) {
            return response()->json(['error' => 'Hotel owner not found'], 404);
        }
        
        return response()->json($hotelOwner);
    }

    public function updateByAuthenticatedUser(Request $request)
    {
        $hotelOwner = HotelOwner::where('user_id', $request->user()->id)->firstOrFail();

        if (!$hotelOwner) {
            return response()->json(['error' => 'You do not have a hotel owner profile.'], 403);
        }

        $validated = $request->validate([
            'hotel_owner_name' => ['sometimes', 'required', 'string', 'max:255'],
            'hotel_owner_nic' => ['sometimes', 'required', 'string', 'max:24'],
            'hotel_owner_dob' => ['sometimes', 'required', 'date'],
            'hotel_owner_address' => ['sometimes', 'required', 'string', 'max:500'],
            'business_mail' => ['sometimes', 'required', 'email'],
            'contact_number' => ['sometimes', 'required', 'string', 'max:15'],
            'whatsapp_number' => ['sometimes', 'nullable', 'string', 'max:15']
        ]);

        DB::beginTransaction();

        try {
            // Update hotel owner fields
            $hotelOwner->fill($request->only([
                'hotel_owner_name', 'hotel_owner_nic', 'hotel_owner_dob', 'hotel_owner_address',
                'business_mail', 'contact_number', 'whatsapp_number'
            ]));

            $hotelOwner->save();

            DB::commit();

            return response()->json([
                'message' => 'Hotel Owner updated successfully!',
                'hotelOwner' => $hotelOwner
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update hotel owner: ' . $e->getMessage()], 500);
        }
    }

    public function deleteByAuthenticatedUser(Request $request)
    {
        $hotelOwner = HotelOwner::where('user_id', $request->user()->id)->firstOrFail();
        $userId = $hotelOwner->user_id;

        DB::beginTransaction();
            
        try {
            $hotelOwnerRole = Role::where('name', 'hotel_owner')->first();
                
            if ($hotelOwnerRole) {
                // Remove the role from the user
                DB::table('role_user')
                    ->where('user_id', $userId)
                    ->where('role_id', $hotelOwnerRole->id)
                    ->delete();
                    
                // DELETE the role requests record
                DB::table('role_requests')
                    ->where('user_id', $hotelOwner->user_id)
                    ->where('role_id', $hotelOwnerRole->id)
                    ->delete();
            }

            $hotelOwner->delete();

            DB::commit();

            return response()->json(['message' => 'Hotel owner deleted successfully!']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to delete hotel owner: ' . $e->getMessage()], 500);
        }
    }

    // Public methods
    public function show($id)
    {
        $hotelOwner = HotelOwner::findOrFail($id);
        return response()->json($hotelOwner);
    }

    public function index()
    {
        $hotelOwners = HotelOwner::get();
        return response()->json($hotelOwners);
    }
}