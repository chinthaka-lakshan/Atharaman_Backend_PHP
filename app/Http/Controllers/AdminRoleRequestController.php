<?php

namespace App\Http\Controllers;

use App\Models\RoleRequest;
use App\Models\Guide;
use App\Models\ShopOwner;
use App\Models\HotelOwner;
use App\Models\VehicleOwner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AdminRoleRequestController extends Controller
{
    public function index() {
        // Check if user is admin
        if (Auth::user()->role !== 'Admin') {
            return response()->json(['error' => 'Unauthorized. Admin access required.'], 403);
        }
        
        return RoleRequest::with(['user', 'role'])->where('status', 'pending')->get();
    }

    public function approve($id) {
        // Check if user is admin
        if (Auth::user()->role !== 'Admin') {
            return response()->json(['error' => 'Unauthorized. Admin access required.'], 403);
        }

        $request = RoleRequest::findOrFail($id);

        DB::transaction(function () use ($request) {
            // Mark as accepted
            $request->update(['status' => 'accepted']);

            // Attach role to user
            $request->user->roles()->attach($request->role_id);

            // Insert into specific role table
            switch ($request->role->name) {
                case 'guide':
                    // Handle file uploads for guide images
                    $guideImages = [];
                    if (!empty($request->extra_data['guideImage'])) {
                        foreach ($request->extra_data['guideImage'] as $imageData) {
                            // If it's a base64 encoded image, save it properly
                            if (strpos($imageData, 'data:image/') === 0) {
                                $imageData = $this->saveBase64Image($imageData, 'guides');
                            }
                            $guideImages[] = $imageData;
                        }
                    }

                    DB::table('guides')->insert([
                        'user_id' => $request->user_id,
                        'guideName' => $request->extra_data['guideName'] ?? null,
                        'guideNic' => $request->extra_data['guideNic'] ?? null,
                        'businessMail' => $request->extra_data['businessMail'] ?? null,
                        'personalNumber' => $request->extra_data['personalNumber'] ?? null,
                        'whatsappNumber' => $request->extra_data['whatsappNumber'] ?? null,
                        'guideImage' => json_encode($guideImages),
                        'languages' => json_encode($request->extra_data['languages'] ?? []),
                        'locations' => json_encode($request->extra_data['locations'] ?? []),
                        'description' => $request->extra_data['description'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    break;

                case 'shop_owner':
                    DB::table('shop_owners')->insert([
                        'user_id' => $request->user_id,
                        'shopOwnerName' => $request->extra_data['shopOwnerName'] ?? null,
                        'shopOwnerNic' => $request->extra_data['shopOwnerNic'] ?? null,
                        'businessMail' => $request->extra_data['businessMail'] ?? null,
                        'contactNumber' => $request->extra_data['contactNumber'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    break;

                case 'hotel_owner':
                    DB::table('hotel_owners')->insert([
                        'user_id' => $request->user_id,
                        'hotelOwnerName' => $request->extra_data['hotelOwnerName'] ?? null,
                        'hotelOwnerNic' => $request->extra_data['hotelOwnerNic'] ?? null,
                        'businessMail' => $request->extra_data['businessMail'] ?? null,
                        'contactNumber' => $request->extra_data['contactNumber'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    break;

                case 'vehicle_owner':
                    DB::table('vehicle_owners')->insert([
                        'user_id' => $request->user_id,
                        'vehicleOwnerName' => $request->extra_data['vehicleOwnerName'] ?? null,
                        'vehicleOwnerNic' => $request->extra_data['vehicleOwnerNic'] ?? null,
                        'businessMail' => $request->extra_data['businessMail'] ?? null,
                        'personalNumber' => $request->extra_data['personalNumber'] ?? null,
                        'whatsappNumber' => $request->extra_data['whatsappNumber'] ?? null,
                        'locations' => json_encode($request->extra_data['locations'] ?? []),
                        'description' => $request->extra_data['description'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    break;
            }
        });

        return response()->json(['message' => 'Request approved']);
    }

    // Helper method to handle base64 images
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

    public function reject($id) {
        // Check if user is admin
        if (Auth::user()->role !== 'Admin') {
            return response()->json(['error' => 'Unauthorized. Admin access required.'], 403);
        }

        $request = RoleRequest::findOrFail($id);
        $request->update(['status' => 'rejected']);
        return response()->json(['message' => 'Request rejected']);
    }
}