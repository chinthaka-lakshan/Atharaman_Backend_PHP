<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Guide;
use App\Models\GuideImage;
use App\Models\Role;
use App\Models\User;
use App\Models\Review;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class GuideController extends Controller
{
    // Admin side methods
    public function store(Request $request)
    {
        $request->validate([
            'guide_name' => ['required', 'string', 'max:255'],
            'guide_nic' => ['required', 'string', 'max:24'],
            'guide_dob' => ['required', 'date'],
            'guide_gender' => ['required', 'string', 'max:15'],
            'guide_address' => ['required', 'string', 'max:500'],
            'business_mail' => ['required', 'email'],
            'contact_number' => ['required', 'string', 'max:15'],
            'whatsapp_number' => ['nullable', 'string', 'max:15'],
            'short_description' => ['required', 'string', 'max:1000'],
            'long_description' => ['nullable', 'string', 'max:10000'],
            'languages' => ['nullable', 'array'],
            'locations' => ['nullable', 'array'],
            'user_id' => ['required', 'exists:users,id'],
            'guideImage' => ['nullable', 'array', 'max:5'],
            'guideImage.*' => ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048']
        ]);

        // Check if NIC already exists in guides table
        $existingGuide = Guide::where('guide_nic', $request->guide_nic)->first();
        if ($existingGuide) {
            return response()->json(['error' => 'This NIC is already registered as a guide in our system'], 422);
        }

        // Get the guide role
        $guideRole = Role::where('name', 'guide')->first();
        
        if (!$guideRole) {
            return response()->json(['error' => 'Guide role not found'], 404);
        }

        // Get the user
        $user = User::findOrFail($request->user_id);

        // Start transaction for data consistency
        DB::beginTransaction();

        try {
            // Create guide
            $guide = Guide::create([
                'guide_name' => $request->guide_name,
                'guide_nic' => $request->guide_nic,
                'guide_dob' => $request->guide_dob,
                'guide_gender' => $request->guide_gender,
                'guide_address' => $request->guide_address,
                'business_mail' => $request->business_mail,
                'contact_number' => $request->contact_number,
                'whatsapp_number' => $request->whatsapp_number,
                'short_description' => $request->short_description,
                'long_description' => $request->long_description,
                'languages' => $request->languages,
                'locations' => $request->locations,
                'user_id' => $request->user_id
            ]);

            // Attach guide role to user if not already attached
            // Use syncWithoutDetaching to avoid duplicates
            $user->roles()->syncWithoutDetaching([$guideRole->id]);

            // Handle image uploads with guide-specific folder
            if ($request->hasFile('guideImage')) {
                $this->processImages($guide, $request->file('guideImage'));
            }

            DB::commit();

            // Load images for response
            $guide->load('images');

            return response()->json([
                'message' => 'Guide created successfully!',
                'guide' => $guide
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create guide: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $guide = Guide::with('images')->findOrFail($id);

        $validated = $request->validate([
            'guide_name' => ['sometimes', 'required', 'string', 'max:255'],
            'guide_nic' => ['sometimes', 'required', 'string', 'max:24'],
            'guide_dob' => ['sometimes', 'required', 'date'],
            'guide_gender' => ['sometimes', 'required', 'string', 'max:15'],
            'guide_address' => ['sometimes', 'required', 'string', 'max:500'],
            'business_mail' => ['sometimes', 'required', 'email'],
            'contact_number' => ['sometimes', 'required', 'string', 'max:15'],
            'whatsapp_number' => ['sometimes', 'nullable', 'string', 'max:15'],
            'short_description' => ['sometimes', 'required', 'string', 'max:1000'],
            'long_description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'languages' => ['sometimes', 'nullable', 'array'],
            'locations' => ['sometimes', 'nullable', 'array'],
            'guideImage' => ['sometimes', 'nullable', 'array', 'max:5'],
            'guideImage.*' => ['sometimes', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'removedImages' => ['sometimes', 'array'],
            'removedImages.*' => ['sometimes', 'integer', 'exists:guide_images,id']
        ]);

        // Check if NIC already exists in guides table (excluding current guide)
        if ($request->has('guide_nic') && $request->guide_nic !== $guide->guide_nic) {
            $existingGuide = Guide::where('guide_nic', $request->guide_nic)
                ->where('id', '!=', $id)
                ->first();
            if ($existingGuide) {
                return response()->json(['error' => 'This NIC is already registered as a guide in our system'], 422);
            }
        }

        DB::beginTransaction();

        try {
            // Handle removed images FIRST
            if ($request->has('removedImages') && !empty($request->removedImages)) {
                $removedImages = GuideImage::where('guide_id', $guide->id)
                    ->whereIn('id', $request->removedImages)
                    ->get();

                foreach ($removedImages as $image) {
                    // Delete from storage
                    if (Storage::disk('public')->exists($image->image_path)) {
                        Storage::disk('public')->delete($image->image_path);
                    }
                    // Delete from database
                    $image->delete();
                }
            }

            // Handle new image uploads with guide-specific folder
            if ($request->hasFile('guideImage')) {
                $this->processImages($guide, $request->file('guideImage'));
            }

            $guide->languages = $request->has('languages') ? $request->languages : null;
            $guide->locations = $request->has('locations') ? $request->locations : null;

            // Update guide fields
            $guide->fill($request->only([
                'guide_name', 'guide_nic', 'guide_dob', 'guide_gender',
                'guide_address', 'business_mail', 'contact_number',
                'whatsapp_number', 'short_description', 'long_description'
            ]));

            $guide->save();

            // Reorder images to maintain consistent indexing
            $this->reorderImages($guide->id);

            DB::commit();

            // Refresh with images
            $guide->load('images');

            return response()->json([
                'message' => 'Guide updated successfully!',
                'guide' => $guide
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update guide: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $guide = Guide::with('images')->findOrFail($id);
        $userId = $guide->user_id;

        DB::beginTransaction();

        try {
            // Delete the entire guide folder from storage
            $guideFolder = "guides/{$guide->id}";
            if (Storage::disk('public')->exists($guideFolder)) {
                Storage::disk('public')->deleteDirectory($guideFolder);
            }

            // Delete associated images from database
            $guide->images()->delete();

            $guideRole = Role::where('name', 'guide')->first();

            if ($guideRole) {
                // Remove the role from the user
                DB::table('role_user')
                    ->where('user_id', $userId)
                    ->where('role_id', $guideRole->id)
                    ->delete();
                    
                // DELETE the role requests record
                DB::table('role_requests')
                    ->where('user_id', $guide->user_id)
                    ->where('role_id', $guideRole->id)
                    ->delete();
            }

            $guide->delete();

            DB::commit();

            return response()->json(['message' => 'Guide deleted successfully!']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to delete guide: ' . $e->getMessage()], 500);
        }
    }

    // User side methods
    public function getByAuthenticatedUser(Request $request)
    {
        $guide = Guide::with('images')->where('user_id', $request->user()->id)->first();
        
        if (!$guide) {
            return response()->json(['error' => 'Guide not found'], 404);
        }
        
        return response()->json($guide);
    }

    public function updateByAuthenticatedUser(Request $request)
    {
        $guide = Guide::with('images')->where('user_id', $request->user()->id)->firstOrFail();

        if (!$guide) {
            return response()->json(['error' => 'You do not have a guide profile.'], 403);
        }

        $validated = $request->validate([
            'guide_name' => ['sometimes', 'required', 'string', 'max:255'],
            'guide_nic' => ['sometimes', 'required', 'string', 'max:24'],
            'guide_dob' => ['sometimes', 'required', 'date'],
            'guide_gender' => ['sometimes', 'required', 'string', 'max:15'],
            'guide_address' => ['sometimes', 'required', 'string', 'max:500'],
            'business_mail' => ['sometimes', 'required', 'email'],
            'contact_number' => ['sometimes', 'required', 'string', 'max:15'],
            'whatsapp_number' => ['sometimes', 'nullable', 'string', 'max:15'],
            'short_description' => ['sometimes', 'required', 'string', 'max:1000'],
            'long_description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'languages' => ['sometimes', 'nullable', 'array'],
            'locations' => ['sometimes', 'nullable', 'array'],
            'guideImage' => ['sometimes', 'nullable', 'array', 'max:5'],
            'guideImage.*' => ['sometimes', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'removedImages' => ['sometimes', 'array'],
            'removedImages.*' => ['sometimes', 'integer', 'exists:guide_images,id']
        ]);

        DB::beginTransaction();

        try {
            // Handle removed images FIRST
            if ($request->has('removedImages') && !empty($request->removedImages)) {
                $removedImages = GuideImage::where('guide_id', $guide->id)
                    ->whereIn('id', $request->removedImages)
                    ->get();

                foreach ($removedImages as $image) {
                    // Delete from storage
                    if (Storage::disk('public')->exists($image->image_path)) {
                        Storage::disk('public')->delete($image->image_path);
                    }
                    // Delete from database
                    $image->delete();
                }
            }

            // Handle new image uploads with guide-specific folder
            if ($request->hasFile('guideImage')) {
                $this->processImages($guide, $request->file('guideImage'));
            }

            $guide->languages = $request->has('languages') ? $request->languages : null;
            $guide->locations = $request->has('locations') ? $request->locations : null;

            // Update guide fields
            $guide->fill($request->only([
                'guide_name', 'guide_nic', 'guide_dob', 'guide_gender',
                'guide_address', 'business_mail', 'contact_number',
                'whatsapp_number', 'short_description', 'long_description'
            ]));

            $guide->save();

            // Reorder images to maintain consistent indexing
            $this->reorderImages($guide->id);

            DB::commit();

            // Refresh with images
            $guide->load('images');

            return response()->json([
                'message' => 'Guide updated successfully!',
                'guide' => $guide
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update guide: ' . $e->getMessage()], 500);
        }
    }

    public function deleteByAuthenticatedUser(Request $request)
    {
        $guide = Guide::with('images')->where('user_id', $request->user()->id)->firstOrFail();
        $userId = $guide->user_id;

        DB::beginTransaction();
    
        try {
            // Delete the entire guide folder from storage
            $guideFolder = "guides/{$guide->id}";
            if (Storage::disk('public')->exists($guideFolder)) {
                Storage::disk('public')->deleteDirectory($guideFolder);
            }

            // Delete associated images from database
            $guide->images()->delete();

            $guideRole = Role::where('name', 'guide')->first();
                
            if ($guideRole) {
                // Remove the role from the user
                DB::table('role_user')
                    ->where('user_id', $userId)
                    ->where('role_id', $guideRole->id)
                    ->delete();
                    
                // DELETE the role requests record
                DB::table('role_requests')
                    ->where('user_id', $guide->user_id)
                    ->where('role_id', $guideRole->id)
                    ->delete();
            }

            $guide->delete();

            DB::commit();

            return response()->json(['message' => 'Guide deleted successfully!']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to delete guide: ' . $e->getMessage()], 500);
        }
    }

    // Process and store images for a guide in guide-specific folder
    private function processImages(Guide $guide, array $images)
    {
        $currentCount = $guide->images()->count();

        // Validate image count (max 5)
        if (($currentCount + count($images)) > 5) {
            throw new \Exception('Maximum 5 images allowed. Current: ' . $currentCount);
        }

        $orderIndex = $guide->images()->max('order_index') ?? -1;

        foreach ($images as $image) {
            $orderIndex++;

            // Store in guide-specific folder: guides/{id}/filename.jpg
            $folder = "guides/{$guide->id}";
            $filename = $this->generateUniqueFilename($image, $orderIndex);
            $path = $image->storeAs($folder, $filename, 'public');

            GuideImage::create([
                'guide_id' => $guide->id,
                'image_path' => $path,
                'order_index' => $orderIndex,
                'alt_text' => "{$guide->guide_name} - Image " . ($orderIndex + 1)
            ]);
        }
    }

    // Generate unique filename to avoid conflicts
    private function generateUniqueFilename($image, $index)
    {
        $extension = $image->getClientOriginalExtension();
        $timestamp = time();
        return "image_{$index}_{$timestamp}.{$extension}";
    }

    // Helper method to reorder images
    private function reorderImages($guideId)
    {
        $images = GuideImage::where('guide_id', $guideId)
            ->orderBy('order_index')
            ->get();

        foreach ($images as $index => $image) {
            $image->update(['order_index' => $index]);
        }
    }

    // Public methods
    public function show($id)
    {
        $guide = Guide::with(['images', 'reviews.user'])
                    ->withCount('reviews')
                    ->withAvg('reviews', 'rating')
                    ->findOrFail($id);

        return response()->json($guide);
    }

    public function index()
    {
        $guides = Guide::with('images')
                    ->withCount('reviews')
                    ->withAvg('reviews', 'rating')
                    ->get();

        return response()->json($guides);
    }

    public function getByLocation($location)
    {
        $guides = Guide::with('images')
                    ->where('locations', 'LIKE', "%{$location}%")
                    ->get();

        return response()->json($guides);
    }
}