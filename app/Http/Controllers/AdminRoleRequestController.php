<?php

namespace App\Http\Controllers;

use App\Models\RoleRequest;
use App\Models\Guide;
use App\Models\GuideImage;
use App\Models\ShopOwner;
use App\Models\HotelOwner;
use App\Models\VehicleOwner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail; // Added for email functionality
use App\Mail\RoleRequestApproved;    // Added Mailable
use App\Mail\RoleRequestRejected;    // Added Mailable

class AdminRoleRequestController extends Controller
{
    /**
     * Display a list of pending role requests.
     */
    public function index() {
        // Check if user is admin
        if (Auth::user()->role !== 'Admin') {
            return response()->json(['error' => 'Unauthorized. Admin access required.'], 403);
        }
        
        return RoleRequest::with(['user', 'role'])->where('status', 'pending')->get();
    }

    /**
     * Approve a specific role request.
     */
    public function approve($id) {
        // Check if user is admin
        if (Auth::user()->role !== 'Admin') {
            Log::error('Unauthorized approval attempt', [
                'user_id' => Auth::id(),
                'request_id' => $id,
                'user_role' => Auth::user()->role
            ]);
            return response()->json(['error' => 'Unauthorized. Admin access required.'], 403);
        }

        // Fetch with user and role for profile creation and email
        $request = RoleRequest::with(['user', 'role'])->findOrFail($id);
        
        Log::info('Admin approval process started', [
            'request_id' => $id,
            'user_id' => $request->user_id,
            'role_id' => $request->role_id,
            'role_name' => $request->role->name,
            'admin_id' => Auth::id()
        ]);

        DB::transaction(function () use ($request) {
            try {
                Log::debug('Database transaction started');
                
                // Check if user already has this role
                if ($request->user->roles()->where('role_id', $request->role_id)->exists()) {
                    Log::warning('User already has this role', [
                        'user_id' => $request->user_id,
                        'role_id' => $request->role_id,
                        'role_name' => $request->role->name
                    ]);
                    throw new \Exception('User already has this role');
                }

                // Mark as accepted
                Log::debug('Updating request status to accepted');
                $request->update(['status' => 'accepted']);
                Log::info('Request status updated to accepted');

                // Attach role to user
                Log::debug('Attaching role to user');
                $request->user->roles()->attach($request->role_id);
                Log::info('Role attached to user successfully', [
                    'user_id' => $request->user_id,
                    'role_id' => $request->role_id
                ]);

                // Insert into specific role table
                switch ($request->role->name) {
                    case 'guide':
                        Log::debug('Starting guide profile creation');
                        $this->createGuideProfile($request);
                        break;

                    case 'shop_owner':
                        Log::debug('Starting shop owner profile creation');
                        $this->createShopOwnerProfile($request);
                        break;

                    case 'hotel_owner':
                        Log::debug('Starting hotel owner profile creation');
                        $this->createHotelOwnerProfile($request);
                        break;

                    case 'vehicle_owner':
                        Log::debug('Starting vehicle owner profile creation');
                        $this->createVehicleOwnerProfile($request);
                        break;
                }

                Log::info('Admin approval process completed successfully', [
                    'request_id' => $request->id,
                    'role_name' => $request->role->name,
                    'user_id' => $request->user_id
                ]);

            } catch (\Exception $e) {
                Log::error('Error during approval process', [
                    'request_id' => $request->id,
                    'error_message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e; // Re-throw to trigger transaction rollback
            }
        });

        // ⭐ NEW: Send approval email after successful transaction
        try {
            Log::debug('Attempting to send approval email', ['user_email' => $request->user->email]);
            Mail::to($request->user->email)->send(new RoleRequestApproved($request));
            Log::info('Approval email queued successfully', ['user_id' => $request->user_id]);
        } catch (\Exception $e) {
            Log::warning('Failed to send approval email', [
                'user_id' => $request->user_id,
                'error' => $e->getMessage()
            ]);
        }
        
        return response()->json(['message' => 'Request approved']);
    }

    /**
     * Create guide profile with detailed logging
     */
    private function createGuideProfile($request)
    {
        try {
            Log::debug('Validating guide data', [
                'extra_data_keys' => array_keys($request->extra_data ?? [])
            ]);

            // Validate required fields
            $requiredFields = ['guide_name', 'guide_nic', 'guide_dob', 'guide_gender', 'guide_address'];
            foreach ($requiredFields as $field) {
                if (empty($request->extra_data[$field])) {
                    throw new \Exception("Missing required field: {$field}");
                }
            }

            Log::debug('Creating guide record');
            $guide = Guide::create([
                'user_id' => $request->user_id,
                'guide_name' => $request->extra_data['guide_name'] ?? null,
                'guide_nic' => $request->extra_data['guide_nic'] ?? null,
                'guide_dob' => $request->extra_data['guide_dob'] ?? null,
                'guide_gender' => $request->extra_data['guide_gender'] ?? null,
                'guide_address' => $request->extra_data['guide_address'] ?? null,
                'business_mail' => $request->extra_data['business_mail'] ?? null,
                'contact_number' => $request->extra_data['contact_number'] ?? null,
                'whatsapp_number' => $request->extra_data['whatsapp_number'] ?? null,
                'short_description' => $request->extra_data['short_description'] ?? null,
                'long_description' => $request->extra_data['long_description'] ?? null,
                'languages' => $request->extra_data['languages'] ?? [],
                'locations' => $request->extra_data['locations'] ?? []
            ]);

            Log::info('Guide profile created successfully', [
                'guide_id' => $guide->id,
                'guide_name' => $guide->guide_name
            ]);

            // Process and move guide images to permanent location (only if images exist)
            if (!empty($request->extra_data['guide_images_processed'])) {
                foreach ($request->extra_data['guide_images_processed'] as $imageInfo) {
                    $this->moveImageToGuideFolder($imageInfo['path'], $guide->id, $imageInfo['order_index'], $imageInfo['alt_text']);
                }
            }

        } catch (\Exception $e) {
            Log::error('Guide profile creation failed', [
                'user_id' => $request->user_id,
                'error' => $e->getMessage(),
                'extra_data_sample' => array_slice($request->extra_data ?? [], 0, 3) // Log first 3 items for context
            ]);
            throw $e;
        }
    }

    /**
     * Create shop owner profile with logging
     */
    private function createShopOwnerProfile($request)
    {
        try {
            Log::debug('Creating shop owner record');
            $shopOwner = ShopOwner::create([
                'user_id' => $request->user_id,
                'shop_owner_name' => $request->extra_data['shop_owner_name'] ?? null,
                'shop_owner_nic' => $request->extra_data['shop_owner_nic'] ?? null,
                'shop_owner_dob' => $request->extra_data['shop_owner_dob'] ?? null,
                'shop_owner_address' => $request->extra_data['shop_owner_address'] ?? null,
                'business_mail' => $request->extra_data['business_mail'] ?? null,
                'contact_number' => $request->extra_data['contact_number'] ?? null,
                'whatsapp_number' => $request->extra_data['whatsapp_number'] ?? null,
            ]);

            Log::info('Shop owner profile created successfully', [
                'shop_owner_id' => $shopOwner->id,
                'shop_owner_name' => $shopOwner->shop_owner_name
            ]);

        } catch (\Exception $e) {
            Log::error('Shop owner profile creation failed', [
                'user_id' => $request->user_id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create hotel owner profile with logging
     */
    private function createHotelOwnerProfile($request)
    {
        try {
            Log::debug('Creating hotel owner record');
            $hotelOwner = HotelOwner::create([
                'user_id' => $request->user_id,
                'hotel_owner_name' => $request->extra_data['hotel_owner_name'] ?? null,
                'hotel_owner_nic' => $request->extra_data['hotel_owner_nic'] ?? null,
                'hotel_owner_dob' => $request->extra_data['hotel_owner_dob'] ?? null,
                'hotel_owner_address' => $request->extra_data['hotel_owner_address'] ?? null,
                'business_mail' => $request->extra_data['business_mail'] ?? null,
                'contact_number' => $request->extra_data['contact_number'] ?? null,
                'whatsapp_number' => $request->extra_data['whatsapp_number'] ?? null,
            ]);

            Log::info('Hotel owner profile created successfully', [
                'hotel_owner_id' => $hotelOwner->id,
                'hotel_owner_name' => $hotelOwner->hotel_owner_name
            ]);

        } catch (\Exception $e) {
            Log::error('Hotel owner profile creation failed', [
                'user_id' => $request->user_id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create vehicle owner profile with logging
     */
    private function createVehicleOwnerProfile($request)
    {
        try {
            Log::debug('Creating vehicle owner record');
            $vehicleOwner = VehicleOwner::create([
                'user_id' => $request->user_id,
                'vehicle_owner_name' => $request->extra_data['vehicle_owner_name'] ?? null,
                'vehicle_owner_nic' => $request->extra_data['vehicle_owner_nic'] ?? null,
                'vehicle_owner_dob' => $request->extra_data['vehicle_owner_dob'] ?? null,
                'vehicle_owner_address' => $request->extra_data['vehicle_owner_address'] ?? null,
                'business_mail' => $request->extra_data['business_mail'] ?? null,
                'contact_number' => $request->extra_data['contact_number'] ?? null,
                'whatsapp_number' => $request->extra_data['whatsapp_number'] ?? null,
                'locations' => $request->extra_data['locations'] ?? [],
            ]);

            Log::info('Vehicle owner profile created successfully', [
                'vehicle_owner_id' => $vehicleOwner->id,
                'vehicle_owner_name' => $vehicleOwner->vehicle_owner_name
            ]);

        } catch (\Exception $e) {
            Log::error('Vehicle owner profile creation failed', [
                'user_id' => $request->user_id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Reject a specific role request.
     */
    public function reject($id) {
        if (Auth::user()->role !== 'Admin') {
            Log::error('Unauthorized rejection attempt', [
                'user_id' => Auth::id(),
                'request_id' => $id
            ]);
            return response()->json(['error' => 'Unauthorized. Admin access required.'], 403);
        }

        // Fetch with user and role for email
        $request = RoleRequest::with(['user', 'role'])->findOrFail($id);
        
        Log::info('Admin rejecting request', [
            'request_id' => $id,
            'user_id' => $request->user_id,
            'role_id' => $request->role_id,
            'admin_id' => Auth::id()
        ]);
        
        // Clean up any temporary images before rejecting
        $this->cleanupTemporaryImages($request->extra_data);
        
        $request->update(['status' => 'rejected']);
        
        Log::info('Request rejected successfully', [
            'request_id' => $id
        ]);

        // ⭐ NEW: Send rejection email after successful update
        try {
            Log::debug('Attempting to send rejection email', ['user_email' => $request->user->email]);
            Mail::to($request->user->email)->send(new RoleRequestRejected($request));
            Log::info('Rejection email queued successfully', ['user_id' => $request->user_id]);
        } catch (\Exception $e) {
            Log::warning('Failed to send rejection email', [
                'user_id' => $request->user_id,
                'error' => $e->getMessage()
            ]);
        }
        
        return response()->json(['message' => 'Request rejected']);
    }

    /**
     * Move temporary guide images to permanent guide folder
     */
    private function moveImageToGuideFolder($tempImagePath, $guideId, $orderIndex, $altText)
    {
        if (!Storage::disk('public')->exists($tempImagePath)) {
            return;
        }

        // Create guide-specific folder
        $guideFolder = "guides/{$guideId}";
        
        // Generate new filename
        $extension = pathinfo($tempImagePath, PATHINFO_EXTENSION);
        $newFilename = "image_{$guideId}_{$orderIndex}.{$extension}";
        $newPath = "{$guideFolder}/{$newFilename}";

        // Move file from temp to permanent location
        if (Storage::disk('public')->exists($tempImagePath)) {
            Storage::disk('public')->move($tempImagePath, $newPath);
            
            // Create guide image record
            GuideImage::create([
                'guide_id' => $guideId,
                'image_path' => $newPath,
                'order_index' => $orderIndex,
                'alt_text' => $altText
            ]);
        }
    }

    /**
     * Clean up temporary images if request is rejected
     */
    private function cleanupTemporaryImages($extraData)
    {
        if (!empty($extraData['guide_images_processed'])) {
            foreach ($extraData['guide_images_processed'] as $imageInfo) {
                if (Storage::disk('public')->exists($imageInfo['path'])) {
                    Storage::disk('public')->delete($imageInfo['path']);
                }
            }
        }
    }

    /**
     * Get role request statistics.
     */
    public function getStatistics()
    {
        if (Auth::user()->role !== 'Admin') {
            return response()->json(['error' => 'Unauthorized. Admin access required.'], 403);
        }

        $stats = RoleRequest::selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = "accepted" THEN 1 ELSE 0 END) as accepted,
            SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected
        ')->first();

        return response()->json([
            'total' => (int) $stats->total,
            'pending' => (int) $stats->pending,
            'accepted' => (int) $stats->accepted,
            'rejected' => (int) $stats->rejected
        ]);
    }
}