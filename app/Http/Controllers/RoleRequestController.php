<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\RoleRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleRequestController extends Controller
{
    public function store(Request $request) {
        $request->validate([
            'role_id' => 'required|exists:roles,id'
        ]);

        $role = Role::find($request->role_id);
        $user = Auth::user();

        // Check if already has role
        if ($user->roles()->where('role_id', $role->id)->exists()) {
            return response()->json(['message' => 'You already have this role'], 400);
        }

        // Check if request already pending
        if (RoleRequest::where('user_id', $user->id)
                ->where('role_id', $role->id)
                ->where('status', 'pending')
                ->exists()) {
            return response()->json(['message' => 'Request already pending'], 400);
        }

        // Dynamic validation based on role
        $extraDataRules = [];
        switch ($role->name) {
            case 'guide':
                $extraDataRules = [
                    'extra_data.guideName' => 'required|string|max:20',
                    'extra_data.guideNic' => 'required|string|max:12',
                    'extra_data.businessMail' => 'required|email|max:100',
                    'extra_data.personalNumber' => 'required|string|max:15',
                    'extra_data.whatsappNumber' => 'required|string|max:15',
                    'extra_data.guideImage' => 'required|array',
                    'extra_data.languages' => 'required|array',
                    'extra_data.locations' => 'required|array',
                    'extra_data.description' => 'required|string|max:500'
                ];
                break;

            case 'shop_owner':
                $extraDataRules = [
                    'extra_data.shopOwnerName' => 'required|string|max:100',
                    'extra_data.shopOwnerNic' => 'required|string|max:12',
                    'extra_data.businessMail' => 'required|email|max:100',
                    'extra_data.contactNumber' => 'required|string|max:15',
                ];
                break;

            case 'hotel_owner':
                $extraDataRules = [
                    'extra_data.hotelOwnerName' => 'required|string|max:100',
                    'extra_data.hotelOwnerNic' => 'required|string|max:12',
                    'extra_data.businessMail' => 'required|email|max:100',
                    'extra_data.contactNumber' => 'required|string|max:15',
                ];
                break;

            case 'vehicle_owner':
                $extraDataRules = [
                    'extra_data.vehicleOwnerName' => 'required|string|max:100',
                    'extra_data.vehicleOwnerNic' => 'required|string|max:12',
                    'extra_data.businessMail' => 'required|email|max:100',
                    'extra_data.personalNumber' => 'required|string|max:15',
                    'extra_data.whatsappNumber' => 'required|string|max:15',
                    'extra_data.locations' => 'required|array',
                    'extra_data.description' => 'required|string|max:500'
                ];
                break;
        }

        $request->validate($extraDataRules);

        // Save request
        RoleRequest::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'extra_data' => $request->extra_data,
            'status' => 'pending'
        ]);

        return response()->json(['message' => 'Request submitted successfully']);
    }
}
