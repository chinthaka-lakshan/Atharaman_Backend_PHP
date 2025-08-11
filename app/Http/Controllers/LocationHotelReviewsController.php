<?php

namespace App\Http\Controllers;
use App\Models\LocationHotelReviews;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class LocationHotelReviewsController extends Controller
{
    public function index()
    {
        // Logic to retrieve and return all location hotel reviews
        $locationHotelReviews = LocationHotelReviews::all();
        return response()->json($locationHotelReviews);
    }

    public function show($id)
    {
        // Logic to retrieve and return a specific location hotel review by ID
        $locationHotelReview = LocationHotelReviews::find($id);
        if ($locationHotelReview) {
            return response()->json($locationHotelReview);
        }
        return response()->json(['message' => 'Location hotel review not found'], 404);
    }

    public function store(Request $request)
    {
        // Logic to validate and create a new location hotel review
        $validatedData = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'type' => 'required|string|in:hotel,restaurant',
            'reviewImages' => 'nullable|array',
        ]);
        $images = [];
        if ($request->hasFile('reviewImages')) {
            foreach ($request->file('reviewImages') as $image) {
                $path = $image->store('reviews', 'public'); // saves in storage/app/public/reviews
                $images[] = $path;
            }
            $validatedData['reviewImages'] = json_encode($images);
        } else {
            $validatedData['reviewImages'] = json_encode([]);
        }

        $locationHotelReview = LocationHotelReviews::create([
            'rating' => $validatedData['rating'],
            'comment' => $validatedData['comment'],
            'type' => $validatedData['type'],
            'reviewImages' => $validatedData['reviewImages'],
            'user_id' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Location hotel review created successfully!',
            'locationHotelReview' => $locationHotelReview
        ]);
    }

    public function update(Request $request, $id)
{
    $validatedData = $request->validate([
        'rating' => 'sometimes|required|integer|min:1|max:5',
        'comment' => 'nullable|string|max:1000',
        'type' => 'sometimes|required|string|in:hotel,restaurant',
        'reviewImages' => 'nullable|array',
        'reviewImages.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        'removeImages' => 'nullable|array', // optional: list of images to remove
        'removeImages.*' => 'string'
    ]);

    $locationHotelReview = LocationHotelReviews::find($id);
    if (!$locationHotelReview) {
        return response()->json(['message' => 'Location hotel review not found'], 404);
    }

    // Get existing images
    $existingImages = json_decode($locationHotelReview->reviewImages, true) ?? [];

    // Remove images if requested
    if (!empty($validatedData['removeImages'])) {
        foreach ($validatedData['removeImages'] as $removeImage) {
            if (($key = array_search($removeImage, $existingImages)) !== false) {
                unset($existingImages[$key]);
                \Storage::disk('public')->delete($removeImage);
            }
        }
    }

    // Add new images if uploaded
    if ($request->hasFile('reviewImages')) {
        foreach ($request->file('reviewImages') as $image) {
            $path = $image->store('reviews', 'public');
            $existingImages[] = $path;
        }
    }

    // Update images
    $locationHotelReview->reviewImages = json_encode(array_values($existingImages));

    // Update other fields only if provided
    if ($request->filled('rating')) $locationHotelReview->rating = $validatedData['rating'];
    if ($request->filled('comment')) $locationHotelReview->comment = $validatedData['comment'];
    if ($request->filled('type')) $locationHotelReview->type = $validatedData['type'];

    $locationHotelReview->save();

    return response()->json([
        'message' => 'Location hotel review updated successfully!',
        'locationHotelReview' => $locationHotelReview
    ]);
}


    public function destroy($id)
    {
        // Logic to delete a specific location hotel review
        $locationHotelReview = LocationHotelReviews::find($id);
        if ($locationHotelReview) {
            $locationHotelReview->delete();
            return response()->json(['message' => 'Location hotel review deleted successfully!']);
        }
        return response()->json(['message' => 'Location hotel review not found'], 404);
    }
    
    public function getReviewsByType($type)
    {
        // Logic to retrieve reviews by type (e.g., hotel, restaurant)
        $reviews = LocationHotelReviews::where('type', $type)->get();
        if ($reviews->isEmpty()) {
            return response()->json(['message' => 'No reviews found for this type'], 404);
        }
        return response()->json($reviews);
    }
}
