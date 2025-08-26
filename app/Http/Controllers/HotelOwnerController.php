<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\HotelOwner;
use App\Models\Role;
use App\Models\User;
use App\Models\RoleRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class HotelOwnerController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'hotelOwnerName' => 'required|string|max:255',
            'hotelOwnerNic' => 'required|string|max:255',
            'businessMail' => 'required|email',
            'contactNumber' => 'required|string|max:15',
            'user_id' => 'required|exists:users,id'
        ]);

        // Get the hotel owner role
        $hotelOwnerRole = Role::where('name', 'hotel_owner')->first();
        
        if (!$hotelOwnerRole) {
            return response()->json(['error' => 'Hotel owner role not found'], 404);
        }

        // Get the user
        $user = User::findOrFail($request->user_id);

        try {
            // Use DB transaction for data consistency
            $hotelOwner = DB::transaction(function () use ($request, $user, $hotelOwnerRole) {
                $hotelOwner = HotelOwner::create([
                    'hotelOwnerName' => $request->hotelOwnerName,
                    'hotelOwnerNic' => $request->hotelOwnerNic,
                    'businessMail' => $request->businessMail,
                    'contactNumber' => $request->contactNumber,
                    'user_id' => $request->user_id
                ]);

                // Attach hotel owner role to user if not already attached
                // Use syncWithoutDetaching to avoid duplicates
                $user->roles()->syncWithoutDetaching([$hotelOwnerRole->id]);

                // Create or update role request status to 'accepted'
                RoleRequest::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'role_id' => $hotelOwnerRole->id,
                    ],
                    [
                        'status' => 'accepted',
                        'extra_data' => [
                            'hotelOwnerName' => $request->hotelOwnerName,
                            'hotelOwnerNic' => $request->hotelOwnerNic,
                            'businessMail' => $request->businessMail,
                            'contactNumber' => $request->contactNumber
                        ]
                    ]
                );

                return $hotelOwner;
            });

            return response()->json([
                'message' => 'Hotel Owner created successfully!',
                'hotelOwner' => $hotelOwner
            ]);

        } catch (\Exception $e) {
            \Log::error('Hotel owner creation failed: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to create hotel owner: ' . $e->getMessage()
            ], 500);
        }
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

    public function destroy($id)
    {
        try {
            $hotelOwner = HotelOwner::findOrFail($id);
            $userId = $hotelOwner->user_id;

            DB::transaction(function () use ($hotelOwner, $userId) {
                // Get the hotel owner role
                $hotelOwnerRole = Role::where('name', 'hotel_owner')->first();
                
                if ($hotelOwnerRole) {
                    // Remove the role from the user
                    DB::table('role_user')
                        ->where('user_id', $userId)
                        ->where('role_id', $hotelOwnerRole->id)
                        ->delete();
                }

                $hotelOwner->delete();
            });

            return response()->json(['message' => 'Hotel owner deleted successfully!']);

        } catch (\Exception $e) {
            \Log::error('Hotel owner deletion failed: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to delete hotel owner: ' . $e->getMessage()
            ], 500);
        }
    }
}