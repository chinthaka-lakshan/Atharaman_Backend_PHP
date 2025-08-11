<?php

namespace App\Http\Controllers;
use App\Models\OtherReviews;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class OtherReviewsController extends Controller
{
    public function index()
    {
        // Logic to retrieve and return all other reviews
        $otherReviews = OtherReview::all();
        return response()->json($otherReviews);
    }

    public function show($id)
    {
        // Logic to retrieve and return a specific other review by ID
        $otherReview = OtherReviews::find($id);
        if ($otherReview) {
            return response()->json($otherReview);
        }
        return response()->json(['message' => 'Other review not found'], 404);
    }


    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'review' => 'required|string|max:1000',
            'rating' => 'required|integer|min:1|max:5',
            'type' => 'required|string|in:vehicle,shop,hotel',
        ]);

        $otherReview = OtherReviews::create([
            'review' => $validatedData['review'],
            'rating' => $validatedData['rating'],
            'type' => $validatedData['type'],
            'user_id' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Other review created successfully!',
            'otherReview' => $otherReview
        ]);
    }

    public function update(Request $request, $id)
    {
        // Logic to validate and update an existing other review
        $validatedData = $request->validate([
            'review' => 'required|string|max:1000',
            'rating' => 'required|integer|min:1|max:5',
            'type' => 'required|string|in:vehicle,shop,hotel',
        ]);

        $otherReview = OtherReviews::find($id);
        if ($otherReview) {
            $otherReview->update($validatedData);
            return response()->json([
                'message' => 'Other review updated successfully!',
                'otherReview' => $otherReview
            ]);
        }
        return response()->json(['message' => 'Other review not found'], 404);
    }

    public function destroy($id)
    {
        // Logic to delete an existing other review
        $otherReview = OtherReviews::find($id);
        if ($otherReview) {
            $otherReview->delete();
            return response()->json(['message' => 'Other review deleted successfully!']);
        }
        return response()->json(['message' => 'Other review not found'], 404);
    }
    public function getReviewsByType($type)
    {
        // Logic to retrieve reviews by type (e.g., hotel, restaurant)
        $reviews = OtherReviews::where('type', $type)->get();
        if ($reviews->isEmpty()) {
            return response()->json(['message' => 'No reviews found for this type'], 404);
        }
        return response()->json($reviews);
    }
}
