<?php

namespace App\Http\Controllers;

use App\Models\WebsiteReview;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class WebsiteReviewController extends Controller
{
    public function index()
    {
        // Retrieve all website reviews with user data
        $websiteReviews = WebsiteReview::with('user')->get();
        return response()->json($websiteReviews);
    }

    public function show($id)
    {
        // Retrieve a specific website review with user data
        $websiteReview = WebsiteReview::with('user')->find($id);
        if ($websiteReview) {
            return response()->json($websiteReview);
        }
        return response()->json(['message' => 'Website review not found'], 404);
    }

    public function store(Request $request)
    {
        // Validate the request
        $validatedData = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        // Create the website review
        $websiteReview = WebsiteReview::create([
            'user_id' => Auth::id(),
            'rating' => $validatedData['rating'],
            'comment' => $validatedData['comment'],
        ]);

        return response()->json([
            'message' => 'Website review created successfully!',
            'websiteReview' => $websiteReview->load('user')
        ], 201);
    }

    public function update(Request $request, $id)
    {
        // Validate the request
        $validatedData = $request->validate([
            'rating' => 'sometimes|required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $websiteReview = WebsiteReview::find($id);
        if (!$websiteReview) {
            return response()->json(['message' => 'Website review not found'], 404);
        }

        // Check if user owns the review
        if ($websiteReview->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Update the website review
        $websiteReview->update([
            'rating' => $validatedData['rating'] ?? $websiteReview->rating,
            'comment' => $validatedData['comment'] ?? $websiteReview->comment,
        ]);

        return response()->json([
            'message' => 'Website review updated successfully!',
            'websiteReview' => $websiteReview->load('user')
        ]);
    }

    public function destroy($id)
    {
        $websiteReview = WebsiteReview::find($id);
        if (!$websiteReview) {
            return response()->json(['message' => 'Website review not found'], 404);
        }

        // Check if user owns the review
        if ($websiteReview->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $websiteReview->delete();

        return response()->json(['message' => 'Website review deleted successfully!']);
    }

    public function getUserWebsiteReviews()
    {
        // Get all website reviews by the authenticated user
        $websiteReviews = WebsiteReview::with('user')
            ->where('user_id', Auth::id())
            ->get();

        return response()->json($websiteReviews);
    }

    public function getRecentReviews($limit = 10)
    {
        // Get recent website reviews
        $reviews = WebsiteReview::with('user')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json($reviews);
    }
}