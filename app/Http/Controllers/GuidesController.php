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
            'description' => 'nullable|string'
        ]);
        $images = [];
        if ($request->hasFile('guideImage')) {
            $images = [];
            foreach ($request->file('guideImage') as $image) {
                $path = $image->store('guides', 'public');
                $images[] = $path;
            }
            $guideImagePaths = json_encode($images);
        } else {
            $guideImagePaths = json_encode([]);
        }

        $guide = Guides::create([
            'guideName' => $request->guideName,
            'guideNic' => $request->guideNic,
            'businessMail' => $request->businessMail,
            'personalNumber' => $request->personalNumber,
            'whatsappNumber' => $request->whatsappNumber,
            'guideImage' => $guideImagePaths,
            // 'guideImage' => json_encode($request->guideImage),
            'languages' => json_encode($request->languages),
            'locations' => json_encode($request->locations),
            'description' => $request->description,
            'user_id' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Guide created successfully!',
            'guide' => $guide
        ]);
    }

    public function update(Request $request, $id)
    {
        $guide = Guides::findOrFail($id);

        $validated = $request->validate([
            'guideName' => 'sometimes|required|string|max:255',
            'guideNic' => 'sometimes|required|string|max:255',
            'businessMail' => 'sometimes|required|email',
            'personalNumber' => 'sometimes|required|string|max:15',
            'whatsappNumber' => 'nullable|string|max:15',
            'guideImage' => 'nullable',
            'guideImage.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'languages' => 'nullable|array',
            'locations' => 'nullable|array',
            'description' => 'nullable|string'
        ]);

        // Handle images (support both single and multiple file upload)
        $existingImages = json_decode($guide->guideImage, true) ?? [];
        $newImages = [];
        if ($request->hasFile('guideImage')) {
            $files = $request->file('guideImage');
            // If single file, wrap in array
            if (!is_array($files)) {
                $files = [$files];
            }
            foreach ($files as $image) {
                $path = $image->store('guides', 'public');
                $newImages[] = $path;
            }
        }
        // If new images uploaded, replace, else keep existing
        $guide->guideImage = count($newImages) > 0 ? json_encode($newImages) : json_encode($existingImages);

        // Update other fields if present
        if ($request->filled('guideName')) $guide->guideName = $request->guideName;
        if ($request->filled('guideNic')) $guide->guideNic = $request->guideNic;
        if ($request->filled('businessMail')) $guide->businessMail = $request->businessMail;
        if ($request->filled('personalNumber')) $guide->personalNumber = $request->personalNumber;
        if ($request->filled('whatsappNumber')) $guide->whatsappNumber = $request->whatsappNumber;
        if ($request->has('languages')) $guide->languages = is_array($request->languages) ? json_encode($request->languages) : $guide->languages;
        if ($request->has('locations')) $guide->locations = is_array($request->locations) ? json_encode($request->locations) : $guide->locations;
        if ($request->filled('description')) $guide->description = $request->description;

        $guide->save();

        return response()->json([
            'message' => 'Guide updated successfully!',
            'guide' => $guide
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

    public function delete($id)
    {
        $guide = Guides::findOrFail($id);
        $guide->delete();

        return response()->json(['message' => 'Guide deleted successfully!']);
    }
}
