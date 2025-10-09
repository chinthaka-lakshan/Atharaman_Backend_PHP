<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ShopOwner;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ShopOwnerController extends Controller
{
    // Admin side methods
    public function store(Request $request)
    {
        $request->validate([
            'shop_owner_name' => ['required', 'string', 'max:255'],
            'shop_owner_nic' => ['required', 'string', 'max:24'],
            'shop_owner_dob' => ['required', 'date'],
            'shop_owner_address' => ['required', 'string', 'max:500'],
            'business_mail' => ['required', 'email'],
            'contact_number' => ['required', 'string', 'max:15'],
            'whatsapp_number' => ['nullable', 'string', 'max:15'],
            'user_id' => ['required', 'exists:users,id']
        ]);

        // Check if NIC already exists in shop_owners table
        $existingShopOwner = ShopOwner::where('shop_owner_nic', $request->shop_owner_nic)->first();
        if ($existingShopOwner) {
            return response()->json(['error' => 'This NIC is already registered as a shop owner in our system'], 422);
        }

        // Get the shop owner role
        $shopOwnerRole = Role::where('name', 'shop_owner')->first();
        
        if (!$shopOwnerRole) {
            return response()->json(['error' => 'Shop owner role not found'], 404);
        }

        // Get the user
        $user = User::findOrFail($request->user_id);

        // Start transaction for data consistency
        DB::beginTransaction();

        try {
            // Create shop owner
            $shopOwner = ShopOwner::create([
                'shop_owner_name' => $request->shop_owner_name,
                'shop_owner_nic' => $request->shop_owner_nic,
                'shop_owner_dob' => $request->shop_owner_dob,
                'shop_owner_address' => $request->shop_owner_address,
                'business_mail' => $request->business_mail,
                'contact_number' => $request->contact_number,
                'whatsapp_number' => $request->whatsapp_number,
                'user_id' => $request->user_id
            ]);

            // Attach shop owner role to user if not already attached
            // Use syncWithoutDetaching to avoid duplicates
            $user->roles()->syncWithoutDetaching([$shopOwnerRole->id]);

            DB::commit();

            return response()->json([
                'message' => 'Shop Owner created successfully!',
                'shopOwner' => $shopOwner
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create shop owner: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $shopOwner = ShopOwner::findOrFail($id);

        $validated = $request->validate([
            'shop_owner_name' => ['sometimes', 'required', 'string', 'max:255'],
            'shop_owner_nic' => ['sometimes', 'required', 'string', 'max:24'],
            'shop_owner_dob' => ['sometimes', 'required', 'date'],
            'shop_owner_address' => ['sometimes', 'required', 'string', 'max:500'],
            'business_mail' => ['sometimes', 'required', 'email'],
            'contact_number' => ['sometimes', 'required', 'string', 'max:15'],
            'whatsapp_number' => ['sometimes', 'nullable', 'string', 'max:15']
        ]);

        // Check if NIC already exists in shop_owners table (excluding current shop owner)
        if ($request->has('shop_owner_nic') && $request->shop_owner_nic !== $shopOwner->shop_owner_nic) {
            $existingShopOwner = ShopOwner::where('shop_owner_nic', $request->shop_owner_nic)
                ->where('id', '!=', $id)
                ->first();
            if ($existingShopOwner) {
                return response()->json(['error' => 'This NIC is already registered as a shop owner in our system'], 422);
            }
        }

        DB::beginTransaction();

        try {
            // Update shop owner fields
            $shopOwner->fill($request->only([
                'shop_owner_name', 'shop_owner_nic', 'shop_owner_dob', 'shop_owner_address',
                'business_mail', 'contact_number', 'whatsapp_number'
            ]));

            $shopOwner->save();

            DB::commit();

            return response()->json([
                'message' => 'Shop Owner updated successfully!',
                'shopOwner' => $shopOwner
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update shop owner: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $shopOwner = ShopOwner::findOrFail($id);
        $userId = $shopOwner->user_id;

        DB::beginTransaction();

        try {
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

            DB::commit();

            return response()->json(['message' => 'Shop owner deleted successfully!']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to delete shop owner: ' . $e->getMessage()], 500);
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

        if (!$shopOwner) {
            return response()->json(['error' => 'You do not have a shop owner profile.'], 403);
        }

        $validated = $request->validate([
            'shop_owner_name' => ['sometimes', 'required', 'string', 'max:255'],
            'shop_owner_nic' => ['sometimes', 'required', 'string', 'max:24'],
            'shop_owner_dob' => ['sometimes', 'required', 'date'],
            'shop_owner_address' => ['sometimes', 'required', 'string', 'max:500'],
            'business_mail' => ['sometimes', 'required', 'email'],
            'contact_number' => ['sometimes', 'required', 'string', 'max:15'],
            'whatsapp_number' => ['sometimes', 'nullable', 'string', 'max:15']
        ]);

        DB::beginTransaction();

        try {
            // Update shop owner fields
            $shopOwner->fill($request->only([
                'shop_owner_name', 'shop_owner_nic', 'shop_owner_dob', 'shop_owner_address',
                'business_mail', 'contact_number', 'whatsapp_number'
            ]));

            $shopOwner->save();

            DB::commit();

            return response()->json([
                'message' => 'Shop Owner updated successfully!',
                'shopOwner' => $shopOwner
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update shop owner: ' . $e->getMessage()], 500);
        }
    }

    public function deleteByAuthenticatedUser(Request $request)
    {
        $shopOwner = ShopOwner::where('user_id', $request->user()->id)->firstOrFail();
        $userId = $shopOwner->user_id;

        DB::beginTransaction();
            
        try {
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

            DB::commit();

            return response()->json(['message' => 'Shop owner deleted successfully!']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to delete shop owner: ' . $e->getMessage()], 500);
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
        $shopOwners = ShopOwner::get();
        return response()->json($shopOwners);
    }
}