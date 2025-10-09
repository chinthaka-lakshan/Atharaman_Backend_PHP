<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\RoleRequest;
use App\Models\Guide;
use App\Models\ShopOwner;
use App\Models\HotelOwner;
use App\Models\VehicleOwner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class RoleRequestController extends Controller
{
    public function store(Request $request) {
        $request->validate([
            'role_id' => 'required|exists:roles,id'
        ]);

        $role = Role::find($request->role_id);
        $user = Auth::user();

        // Check if user already has this role
        if ($user->roles()->where('role_id', $role->id)->exists()) {
            return response()->json(['message' => 'You already have this role'], 400);
        }

        // Check for existing pending request
        if (RoleRequest::where('user_id', $user->id)
                ->where('role_id', $role->id)
                ->where('status', 'pending')
                ->exists()) {
            return response()->json(['message' => 'Request already pending'], 400);
        }

        // Dynamic validation based on role
        $extraDataRules = [];
        $nicField = '';
        
        switch ($role->name) {
            case 'guide':
                $extraDataRules = [
                    'extra_data.guide_name' => 'required|string|max:255',
                    'extra_data.guide_nic' => 'required|string|max:24',
                    'extra_data.guide_dob' => 'required|date',
                    'extra_data.guide_gender' => 'required|string|max:15',
                    'extra_data.guide_address' => 'required|string|max:500',
                    'extra_data.business_mail' => 'required|email',
                    'extra_data.contact_number' => 'required|string|max:15',
                    'extra_data.whatsapp_number' => 'nullable|string|max:15',
                    'extra_data.short_description' => 'required|string|max:1000',
                    'extra_data.long_description' => 'nullable|string|max:10000',
                    'extra_data.languages' => 'nullable|array',
                    'extra_data.locations' => 'nullable|array',
                    'extra_data.guide_images' => 'nullable|array|max:5',
                    'extra_data.guide_images.*' => 'nullable|string'
                ];
                $nicField = 'guide_nic';
                break;

            case 'shop_owner':
                $extraDataRules = [
                    'extra_data.shop_owner_name' => 'required|string|max:255',
                    'extra_data.shop_owner_nic' => 'required|string|max:24',
                    'extra_data.shop_owner_dob' => 'required|date',
                    'extra_data.shop_owner_address' => 'required|string|max:500',
                    'extra_data.business_mail' => 'required|email',
                    'extra_data.contact_number' => 'required|string|max:15',
                    'extra_data.whatsapp_number' => 'nullable|string|max:15',
                ];
                $nicField = 'shop_owner_nic';
                break;

            case 'hotel_owner':
                $extraDataRules = [
                    'extra_data.hotel_owner_name' => 'required|string|max:255',
                    'extra_data.hotel_owner_nic' => 'required|string|max:24',
                    'extra_data.hotel_owner_dob' => 'required|date',
                    'extra_data.hotel_owner_address' => 'required|string|max:500',
                    'extra_data.business_mail' => 'required|email',
                    'extra_data.contact_number' => 'required|string|max:15',
                    'extra_data.whatsapp_number' => 'nullable|string|max:15',
                ];
                $nicField = 'hotel_owner_nic';
                break;

            case 'vehicle_owner':
                $extraDataRules = [
                    'extra_data.vehicle_owner_name' => 'required|string|max:255',
                    'extra_data.vehicle_owner_nic' => 'required|string|max:24',
                    'extra_data.vehicle_owner_dob' => 'required|date',
                    'extra_data.vehicle_owner_address' => 'required|string|max:500',
                    'extra_data.business_mail' => 'required|email',
                    'extra_data.contact_number' => 'required|string|max:15',
                    'extra_data.whatsapp_number' => 'nullable|string|max:15',
                    'extra_data.locations' => 'nullable|array',
                ];
                $nicField = 'vehicle_owner_nic';
                break;
        }

        $request->validate($extraDataRules);

        // Check if NIC already exists in the same business role table (for ANY user)
        $nicExists = $this->checkNicExistsInRoleTable($role->name, $request->extra_data[$nicField]);
        if ($nicExists) {
            return response()->json([
                'message' => 'This NIC is already registered as a ' . str_replace('_', ' ', $role->name) . ' in our system'
            ], 400);
        }

        // Handle image uploads for guide role
        $uploadedImages = [];
        if ($role->name === 'guide' && !empty($request->extra_data['guide_images'])) {
            foreach ($request->extra_data['guide_images'] as $index => $imageData) {
                if (is_string($imageData) && strpos($imageData, 'data:image/') === 0) {
                    $imagePath = $this->saveBase64Image($imageData, 'guide-requests');
                    $uploadedImages[] = [
                        'path' => $imagePath,
                        'order_index' => $index,
                        'alt_text' => 'Guide image ' . ($index + 1)
                    ];
                }
            }
            
            if (!empty($uploadedImages)) {
                $request->merge(['extra_data' => array_merge(
                    $request->extra_data,
                    ['guide_images_processed' => $uploadedImages]
                )]);
            }
        }

        // Save request
        RoleRequest::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'extra_data' => $request->extra_data,
            'status' => 'pending'
        ]);

        return response()->json(['message' => 'Request submitted successfully']);
    }

    /**
     * Check if NIC already exists in the specific role table (for any user)
     */
    private function checkNicExistsInRoleTable($roleName, $nic)
    {
        switch ($roleName) {
            case 'guide':
                return Guide::where('guide_nic', $nic)->exists();
                
            case 'shop_owner':
                return ShopOwner::where('shop_owner_nic', $nic)->exists();
                
            case 'hotel_owner':
                return HotelOwner::where('hotel_owner_nic', $nic)->exists();
                
            case 'vehicle_owner':
                return VehicleOwner::where('vehicle_owner_nic', $nic)->exists();
                
            default:
                return false;
        }
    }

    /**
     * Check NIC availability (for real-time validation)
     */
    public function checkNic(Request $request)
    {
        $request->validate([
            'nic' => 'required|string|max:24',
            'role' => 'required|string|in:guide,shop_owner,hotel_owner,vehicle_owner'
        ]);

        $nic = $request->nic;
        $role = $request->role;

        $exists = $this->checkNicExistsInRoleTable($role, $nic);

        if ($exists) {
            $roleName = str_replace('_', ' ', $role);
            return response()->json([
                'available' => false,
                'message' => "This NIC is already registered as a {$roleName} in our system"
            ]);
        }

        return response()->json([
            'available' => true,
            'message' => 'NIC is available'
        ]);
    }

    /**
     * Save base64 image to storage
     */
    private function saveBase64Image($base64Data, $folder = 'guides')
    {
        list($type, $data) = explode(';', $base64Data);
        list(, $data) = explode(',', $data);
        $data = base64_decode($data);
        
        $extension = explode('/', $type)[1];
        $filename = uniqid() . '.' . $extension;
        $path = $folder . '/' . $filename;
        
        Storage::disk('public')->put($path, $data);
        
        return $path;
    }
}