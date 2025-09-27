<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Guides;
use App\Models\GuideImage;
use App\Models\Role;
use App\Models\User;
use App\Models\RoleRequest;
use App\Models\Review;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class GuidesController extends Controller
{
    // Admin side methods
    public function store(Request $request)
    {
        $request->validate([
            'guideName' => ['required', 'string', 'max:500'],
            'guideNic' => ['required', 'string', 'max:255'],
            'businessMail' => ['required', 'email'],
            'personalNumber' => ['required', 'string', 'max:15'],
            'whatsappNumber' => ['nullable', 'string', 'max:15'],
            'description' => ['required', 'string', 'max:7500'],
            'languages' => ['nullable', 'array'],
            'locations' => ['nullable', 'array'],
            'user_id' => ['required', 'exists:users,id'],
            'guideImage' => ['nullable', 'array', 'max:5'],
            'guideImage.*' => ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048']
        ]);

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
            $guide = Guides::create([
                'guideName' => $request->guideName,
                'guideNic' => $request->guideNic,
                'businessMail' => $request->businessMail,
                'personalNumber' => $request->personalNumber,
                'whatsappNumber' => $request->whatsappNumber,
                'description' => $request->description,
                'languages' => $request->languages,
                'locations' => $request->locations,
                'user_id' => $request->user_id
            ]);

            // Attach guide role to user if not already attached
            // Use syncWithoutDetaching to avoid duplicates
            $user->roles()->syncWithoutDetaching([$guideRole->id]);

            // Create or update role request status to 'accepted'
            RoleRequest::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'role_id' => $guideRole->id,
                ],
                [
                    'status' => 'accepted',
                    'extra_data' => [
                        'guideName' => $request->guideName,
                        'guideNic' => $request->guideNic,
                        'businessMail' => $request->businessMail,
                        'personalNumber' => $request->personalNumber,
                        'whatsappNumber' => $request->whatsappNumber,
                        'languages' => $request->languages,
                        'locations' => $request->locations,
                        'description' => $request->description
                    ]
                ]
            );

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
        $guide = Guides::with('images')->findOrFail($id);

        $validated = $request->validate([
            'guideName' => ['sometimes', 'required', 'string', 'max:500'],
            'guideNic' => ['sometimes', 'required', 'string', 'max:255'],
            'businessMail' => ['sometimes', 'required', 'email'],
            'personalNumber' => ['sometimes', 'required', 'string', 'max:15'],
            'whatsappNumber' => ['sometimes', 'nullable', 'string', 'max:15'],
            'description' => ['sometimes', 'required', 'string', 'max:7500'],
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
                'guideName', 'guideNic', 'businessMail', 
                'personalNumber', 'whatsappNumber', 'description'
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
        $guide = Guides::with('images')->findOrFail($id);
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
        $guide = Guides::with('images')->where('user_id', $request->user()->id)->first();
        
        if (!$guide) {
            return response()->json(['error' => 'Guide not found'], 404);
        }
        
        return response()->json($guide);
    }

    public function updateByAuthenticatedUser(Request $request)
    {
        $guide = Guides::with('images')->where('user_id', $request->user()->id)->firstOrFail();

        $validated = $request->validate([
            'guideName' => ['sometimes', 'required', 'string', 'max:500'],
            'guideNic' => ['sometimes', 'required', 'string', 'max:255'],
            'businessMail' => ['sometimes', 'required', 'email'],
            'personalNumber' => ['sometimes', 'required', 'string', 'max:15'],
            'whatsappNumber' => ['sometimes', 'nullable', 'string', 'max:15'],
            'description' => ['sometimes', 'required', 'string', 'max:7500'],
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

            // Update guide fields
            $guide->fill($request->only([
                'guideName', 'guideNic', 'businessMail', 
                'personalNumber', 'whatsappNumber', 'description'
            ]));

            if ($request->has('languages')) {
                $guide->languages = $request->languages;
            }

            if ($request->has('locations')) {
                $guide->locations = $request->locations;
            }

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
        $guide = Guides::with('images')->where('user_id', $request->user()->id)->firstOrFail();
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

    /**
     * Process and store images for a guide in guide-specific folder
     */
    private function processImages(Guides $guide, array $images)
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
                'alt_text' => "{$guide->guideName} - Image " . ($orderIndex + 1)
            ]);
        }
    }

    /**
     * Generate unique filename to avoid conflicts
     */
    private function generateUniqueFilename($image, $index)
    {
        $extension = $image->getClientOriginalExtension();
        $timestamp = time();
        return "image_{$index}_{$timestamp}.{$extension}";
    }

    /**
     * Helper method to reorder images
     */
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
        $guide = Guides::with(['images', 'reviews.user'])
                    ->withCount('reviews')
                    ->withAvg('reviews', 'rating')
                    ->findOrFail($id);

        return response()->json($guide);
    }

    public function index()
    {
        $guides = Guides::with(['images'])
                    ->withCount('reviews')
                    ->withAvg('reviews', 'rating')
                    ->get();

        return response()->json($guides);
    }

    public function getByLocation($location)
    {
        $guides = Guides::with('images')
                    ->where('locations', 'LIKE', "%{$location}%")
                    ->get();
        return response()->json($guides);
    }
}