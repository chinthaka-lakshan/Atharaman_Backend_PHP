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
            'user_id' => 'sometimes|required|exists:users,id'
        ]);

        // Handle image updates
        if ($request->hasFile('guideImage')) {
            // Delete existing images from storage
            foreach ($existingImages as $oldImage) {
                \Storage::disk('public')->delete($oldImage);
            }
            
            // Store new images
            $newImages = [];
            foreach ($request->file('guideImage') as $image) {
                $path = $image->store('guides', 'public');
                $newImages[] = $path;
            }
            $guide->guideImage = $newImages;
        } elseif ($request->input('remove_images') === 'true') {
            // Explicit request to remove all images
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
