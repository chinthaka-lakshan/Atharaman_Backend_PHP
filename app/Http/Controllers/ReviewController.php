<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReviewController extends Controller
{
    public function index()
    {
        // Retrieve all reviews with user data
        $reviews = Review::with('user')->get();
        return response()->json($reviews);
    }

    public function show($id)
    {
        // Retrieve a specific review with user data
        $review = Review::with('user')->find($id);
        if ($review) {
            return response()->json($review);
        }
        return response()->json(['message' => 'Review not found'], 404);
    }

    public function store(Request $request)
    {
        // Validate the request
        $validatedData = $request->validate([
            'entity_type' => 'required|string|in:location,hotel,guide,shop,vehicle',
            'entity_id' => 'required|integer|min:1',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Handle image uploads
        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('reviews', 'public');
                $images[] = $path;
            }
        }

        // Create the review
        $review = Review::create([
            'user_id' => Auth::id(),
            'entity_type' => $validatedData['entity_type'],
            'entity_id' => $validatedData['entity_id'],
            'rating' => $validatedData['rating'],
            'comment' => $validatedData['comment'],
            'images' => $images,
        ]);

        return response()->json([
            'message' => 'Review created successfully!',
            'review' => $review->load('user')
        ], 201);
    }

    public function update(Request $request, $id)
    {
        // Validate the request
        $validatedData = $request->validate([
            'rating' => 'sometimes|required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'remove_images' => 'nullable|array',
            'remove_images.*' => 'string',
        ]);

        $review = Review::find($id);
        if (!$review) {
            return response()->json(['message' => 'Review not found'], 404);
        }

        // Check if user owns the review
        if ($review->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Get existing images
        $existingImages = $review->images ?? [];

        // Remove images if requested
        if (!empty($validatedData['remove_images'])) {
            foreach ($validatedData['remove_images'] as $removeImage) {
                if (($key = array_search($removeImage, $existingImages)) !== false) {
                    unset($existingImages[$key]);
                    Storage::disk('public')->delete($removeImage);
                }
            }
        }

        // Add new images if uploaded
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('reviews', 'public');
                $existingImages[] = $path;
            }
        }

        // Update the review
        $review->update([
            'rating' => $validatedData['rating'] ?? $review->rating,
            'comment' => $validatedData['comment'] ?? $review->comment,
            'images' => array_values($existingImages), // Reindex array
        ]);

        return response()->json([
            'message' => 'Review updated successfully!',
            'review' => $review->load('user')
        ]);
    }

    public function destroy($id)
    {
        $review = Review::find($id);
        if (!$review) {
            return response()->json(['message' => 'Review not found'], 404);
        }

        // Check if user owns the review
        if ($review->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Delete associated images
        if (!empty($review->images)) {
            foreach ($review->images as $image) {
                Storage::disk('public')->delete($image);
            }
        }

        $review->delete();

        return response()->json(['message' => 'Review deleted successfully!']);
    }

    public function getReviewsByEntity($entityType, $entityId)
    {
        // Validate entity type
        if (!in_array($entityType, ['location', 'hotel', 'guide', 'shop', 'vehicle'])) {
            return response()->json(['message' => 'Invalid entity type'], 400);
        }

        $reviews = Review::with('user')
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->get();

        if ($reviews->isEmpty()) {
            return response()->json(['message' => 'No reviews found for this entity'], 404);
        }

        return response()->json($reviews);
    }

    public function getUserReviews()
    {
        // Get all reviews by the authenticated user
        $reviews = Review::with('user')
            ->where('user_id', Auth::id())
            ->get();

        return response()->json($reviews);
    }
}