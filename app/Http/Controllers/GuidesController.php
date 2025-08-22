<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Guides;
use Illuminate\Support\Facades\Auth;

class GuidesController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'guideName' => 'required|string|max:255',
            'guideNic' => 'required|string|max:255',
            'businessMail' => 'required|email',
            'personalNumber' => 'required|string|max:15',
            'whatsappNumber' => 'nullable|string|max:15',
            'guideImage.*'    => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'languages' => 'nullable|array',
            'locations' => 'nullable|array',
            'description' => 'nullable|string',
            'user_id' => 'required|exists:users,id'
        ]);

        $images = [];
        if ($request->hasFile('guideImage')) {
            $images = [];
            foreach ($request->file('guideImage') as $image) {
                $path = $image->store('guides', 'public');
                $images[] = $path;
            }
        }

        $guide = Guides::create([
            'guideName' => $request->guideName,
            'guideNic' => $request->guideNic,
            'businessMail' => $request->businessMail,
            'personalNumber' => $request->personalNumber,
            'whatsappNumber' => $request->whatsappNumber,
            'description' => $request->description,
            'guideImage' => $images,
            'languages' => $request->languages,
            'locations' => $request->locations,
            'user_id' => $request->user_id
        ]);

        return response()->json([
            'message' => 'Guide created successfully!',
            'guide' => $guide
        ]);
    }

    public function update(Request $request, $id)
    {
        $guide = Guides::findOrFail($id);
        $existingImages = $guide->guideImage ?? [];

        $validated = $request->validate([
            'guideName' => 'sometimes|required|string|max:255',
            'guideNic' => 'sometimes|required|string|max:255',
            'businessMail' => 'sometimes|required|email',
            'personalNumber' => 'sometimes|required|string|max:15',
            'whatsappNumber' => 'nullable|string|max:15',
            'guideImage.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'languages' => 'nullable|array',
            'locations' => 'nullable|array',
            'description' => 'nullable|string',
            'remove_images' => 'nullable|array', // For individual image removal
            'remove_images.*' => 'nullable|string' // Image paths to remove
        ]);

        // Handle individual image removal first
        if ($request->has('remove_images') && is_array($request->remove_images)) {
            foreach ($request->remove_images as $imageToRemove) {
                // Find and remove the image from existing images array
                if (($key = array_search($imageToRemove, $existingImages)) !== false) {
                    \Storage::disk('public')->delete($existingImages[$key]);
                    unset($existingImages[$key]);
                }
            }
            // Reindex the array
            $existingImages = array_values($existingImages);
            $guide->guideImage = $existingImages;
        }

        // Handle new image uploads
        if ($request->hasFile('guideImage')) {
            $newImages = [];
            foreach ($request->file('guideImage') as $image) {
                $path = $image->store('guides', 'public');
                $newImages[] = $path;
            }
            
            // If we have existing images after removal, merge with new ones
            if (!empty($existingImages)) {
                $guide->guideImage = array_merge($existingImages, $newImages);
            } else {
                $guide->guideImage = $newImages;
            }
        } elseif ($request->input('remove_all_images') === 'true') {
            // Handle explicit request to remove all images
            foreach ($existingImages as $oldImage) {
                \Storage::disk('public')->delete($oldImage);
            }
            $guide->guideImage = [];
        }

        // Update other fields
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

        return response()->json([
            'message' => 'Guide updated successfully!',
            'guide' => $guide->fresh()
        ]);
    }

    public function show($id)
    {
        $guide = Guides::findOrFail($id);
        return response()->json($guide);
    }
    
    public function index()
    {
        $guides = Guides::all();
        return response()->json($guides);
    }

    public function getByLocation($location)
    {
        $guides = Guides::where('locations', 'LIKE', "%{$location}%")->get();
        return response()->json($guides);
    }

    public function destroy($id)
    {
        $guide = Guides::findOrFail($id);

        // Delete associated images
        $images = $guide->guideImage ?? [];
        foreach ($images as $image) {
            \Storage::disk('public')->delete($image);
        }

        $guide->delete();

        return response()->json(['message' => 'Guide deleted successfully!']);
    }
}
