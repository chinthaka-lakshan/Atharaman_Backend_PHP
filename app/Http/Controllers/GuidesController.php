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
                $path = $image->store('guides', 'public'); // saves in storage/app/public/guides
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
        $request->validate([
            'guideName' => 'sometimes|required|string|max:255',
            'guideNic' => 'sometimes|required|string|max:255',
            'businessMail' => 'sometimes|required|email',
            'personalNumber' => 'sometimes|required|string|max:15',
            'whatsappNumber' => 'nullable|string|max:15',
            'guideImage' => 'nullable|array',
            'languages' => 'nullable|array',
            'locations' => 'nullable|array',
            'description' => 'nullable|string'
        ]);

        $guide = Guides::findOrFail($id);
        $guide->update($request->all());

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
