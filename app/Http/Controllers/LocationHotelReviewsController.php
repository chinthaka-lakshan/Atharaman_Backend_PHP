<?php

namespace App\Http\Controllers;

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
            'reviewImages' => 'nullable|array',
            'reviewImages.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $locationHotelReview = LocationHotelReviews::find($id);
        if (!$locationHotelReview) {
            return response()->json(['message' => 'Location hotel review not found'], 404);
        }

        // Handle images
        $existingImages = json_decode($locationHotelReview->reviewImages, true) ?? [];
        $images = [];
        if ($request->hasFile('reviewImages')) {
            foreach ($request->file('reviewImages') as $image) {
                $path = $image->store('reviews', 'public');
                $images[] = $path;
            }
            $locationHotelReview->reviewImages = json_encode($images);
        } else {
            $locationHotelReview->reviewImages = json_encode($existingImages);
        }

        if ($request->filled('rating')) $locationHotelReview->rating = $request->rating;
        if ($request->filled('comment')) $locationHotelReview->comment = $request->comment;

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
