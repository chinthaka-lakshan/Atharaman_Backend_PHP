<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ShopOwner;
use App\Models\Role;
use App\Models\User;
use App\Models\RoleRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ShopOwnerController extends Controller
{
    // Admin side methods
    public function store(Request $request)
    {
        $request->validate([
            'shopOwnerName' => 'required|string|max:255',
            'shopOwnerNic' => 'required|string|max:255',
            'businessMail' => 'required|email',
            'contactNumber' => 'required|string|max:15',
            'user_id' => 'required|exists:users,id'
        ]);

        // Get the shop owner role
        $shopOwnerRole = Role::where('name', 'shop_owner')->first();
        
        if (!$shopOwnerRole) {
            return response()->json(['error' => 'Shop owner role not found'], 404);
        }

        // Get the user
        $user = User::findOrFail($request->user_id);

        try {
            // Use DB transaction for data consistency
            $shopOwner = DB::transaction(function () use ($request, $user, $shopOwnerRole) {
                $shopOwner = ShopOwner::create([
                    'shopOwnerName' => $request->shopOwnerName,
                    'shopOwnerNic' => $request->shopOwnerNic,
                    'businessMail' => $request->businessMail,
                    'contactNumber' => $request->contactNumber,
                    'user_id' => $request->user_id,
                ]);

                // Attach shop owner role to user if not already attached
                // Use syncWithoutDetaching to avoid duplicates
                $user->roles()->syncWithoutDetaching([$shopOwnerRole->id]);

                // Create or update role request status to 'accepted'
                RoleRequest::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'role_id' => $shopOwnerRole->id,
                    ],
                    [
                        'status' => 'accepted',
                        'extra_data' => [
                            'shopOwnerName' => $request->shopOwnerName,
                            'shopOwnerNic' => $request->shopOwnerNic,
                            'businessMail' => $request->businessMail,
                            'contactNumber' => $request->contactNumber
                        ]
                    ]
                );

                return $shopOwner;
            });

            return response()->json([
                'message' => 'Shop Owner created successfully!',
                'shopOwner' => $shopOwner
            ]);

        } catch (\Exception $e) {
            \Log::error('Shop owner creation failed: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to create shop owner: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $shopOwner = ShopOwner::findOrFail($id);

        $validated = $request->validate([
            'shopOwnerName' => 'sometimes|required|string|max:255',
            'shopOwnerNic' => 'sometimes|required|string|max:255',
            'businessMail' => 'sometimes|required|email',
            'contactNumber' => 'sometimes|required|string|max:15',
            'user_id' => 'sometimes|required|exists:users,id'
        ]);

        // Update other fields
        $shopOwner->fill($request->only([
            'shopOwnerName', 'shopOwnerNic', 'businessMail', 'contactNumber'
        ]));

        $shopOwner->save();

        return response()->json([
            'message' => 'Shop Owner updated successfully!',
            'shopOwner' => $shopOwner->fresh()
        ]);
    }

    public function destroy($id)
    {
        try {
            $shopOwner = ShopOwner::findOrFail($id);
            $userId = $shopOwner->user_id;

            DB::transaction(function () use ($shopOwner, $userId) {
                // Get the shop owner role
                $shopOwnerRole = Role::where('name', 'shop_owner')->first();
                
                if ($shopOwnerRole) {
                    // Remove the role from the user
                    DB::table('role_user')
                        ->where('user_id', $userId)
                        ->where('role_id', $shopOwnerRole->id)
                        ->delete();
                    
                    // DELETE the role requests record
                    DB::table('role_requests')
                        ->where('user_id', $shopOwner->user_id)
                        ->where('role_id', $shopOwnerRole->id)
                        ->delete();
                }

                $shopOwner->delete();
            });

            return response()->json(['message' => 'Shop owner deleted successfully!']);

        } catch (\Exception $e) {
            \Log::error('Shop owner deletion failed: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to delete shop owner: ' . $e->getMessage()
            ], 500);
        }
    }

    // User side methods
    public function getByAuthenticatedUser(Request $request)
    {
        $shopOwner = ShopOwner::where('user_id', $request->user()->id)->first();
        
        if (!$shopOwner) {
            return response()->json(['error' => 'Shop owner not found'], 404);
        }
        
        return response()->json($shopOwner);
    }

    public function updateByAuthenticatedUser(Request $request)
    {
        $shopOwner = ShopOwner::where('user_id', $request->user()->id)->firstOrFail();

        $validated = $request->validate([
            'shopOwnerName' => 'sometimes|required|string|max:255',
            'shopOwnerNic' => 'sometimes|required|string|max:255',
            'businessMail' => 'sometimes|required|email',
            'contactNumber' => 'sometimes|required|string|max:15',
        ]);

        $shopOwner->update($validated);

        return response()->json([
            'message' => 'Shop Owner updated successfully!',
            'shopOwner' => $shopOwner->fresh()
        ]);
    }

    public function deleteByAuthenticatedUser(Request $request)
    {
        try {
            $shopOwner = ShopOwner::where('user_id', $request->user()->id)->firstOrFail();
            
            DB::transaction(function () use ($shopOwner) {
                // Get the shop owner role
                $shopOwnerRole = Role::where('name', 'shop_owner')->first();
                
                if ($shopOwnerRole) {
                    // Remove the role from the user
                    DB::table('role_user')
                        ->where('user_id', $shopOwner->user_id)
                        ->where('role_id', $shopOwnerRole->id)
                        ->delete();
                    
                    // DELETE the role requests record
                    DB::table('role_requests')
                        ->where('user_id', $shopOwner->user_id)
                        ->where('role_id', $shopOwnerRole->id)
                        ->delete();
                }

                $shopOwner->delete();
            });

            return response()->json(['message' => 'Shop owner deleted successfully!']);

        } catch (\Exception $e) {
            \Log::error('Shop owner deletion failed: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to delete shop owner: ' . $e->getMessage()
            ], 500);
        }
    }

    // Public methods
    public function show($id)
    {
        $shopOwner = ShopOwner::findOrFail($id);
        return response()->json($shopOwner);
    }

    public function index()
    {
        $shopOwners = ShopOwner::all();
        return response()->json($shopOwners);
    }
}