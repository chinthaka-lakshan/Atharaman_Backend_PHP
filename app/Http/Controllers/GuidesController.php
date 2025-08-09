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
            'guideImage' => 'nullable|array',
            'languages' => 'nullable|array',
            'locations' => 'nullable|array',
            'description' => 'nullable|string'
        ]);

        $guide = Guides::create([
            'guideName' => $request->guideName,
            'guideNic' => $request->guideNic,
            'businessMail' => $request->businessMail,
            'personalNumber' => $request->personalNumber,
            'whatsappNumber' => $request->whatsappNumber,
            'guideImage' => json_encode($request->guideImage),
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

    public function getGuide()
    {
        $guide = Guides::where('user_id', Auth::id())->first();
        return response()->json($guide);
    }
}
