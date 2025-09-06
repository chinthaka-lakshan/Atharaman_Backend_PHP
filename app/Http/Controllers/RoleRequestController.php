<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\RoleRequest;
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

        // Handle image uploads for guide role
        $uploadedImages = [];
        if ($role->name === 'guide' && !empty($request->extra_data['guideImage'])) {
            foreach ($request->extra_data['guideImage'] as $imageData) {
                // Handle base64 images
                if (is_string($imageData) && strpos($imageData, 'data:image/') === 0) {
                    // This is a base64 image - save it properly
                    $imagePath = $this->saveBase64Image($imageData, 'guides');
                    $uploadedImages[] = $imagePath;
                } else {
                    // This is already a filename or invalid data
                    $uploadedImages[] = $imageData;
                }
            }
            
            // Update the extra_data with processed images
            $request->merge(['extra_data' => array_merge(
                $request->extra_data,
                ['guideImage' => $uploadedImages]
            )]);
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

    // Add this helper method
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