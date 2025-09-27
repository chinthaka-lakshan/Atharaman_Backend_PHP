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
        // Retrieve all reviews with user data - accessible to everyone
        $reviews = Review::with('user')->get();
        return response()->json($reviews);
    }

    public function show($id)
    {
        // Retrieve a specific review with user data - accessible to everyone
        $review = Review::with('user')->find($id);
        if ($review) {
            return response()->json($review);
        }
        return response()->json(['message' => 'Review not found'], 404);
    }

    public function store(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthorized. Please log in to submit reviews.'], 401);
        }

        $validatedData = $request->validate([
            'entity_type' => 'required|string|in:location,hotel,guide,shop,vehicle',
            'entity_id' => 'required|integer|min:1',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'review_images' => 'nullable|array|max:5',
            'review_images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);
        DB::beginTransaction();
        try {
            $review = Review::create([
                'user_id' => Auth::id(),
                'entity_type' => $validatedData['entity_type'],
                'entity_id' => $validatedData['entity_id'],
                'rating' => $validatedData['rating'],
                'comment' => $validatedData['comment'] ?? null,
            ]);
            // Handle image uploads
            if ($request->hasFile('review_images')) {
                $this->processImages($review, $request->file('review_images'));
            }
            DB::commit();

            $review->load('images');

        return response()->json([
            'message' => 'Review created successfully!',
            'review' => $review->load('user')
        ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create review', 'error' => $e->getMessage()], 500);
        }
    }


    public function update(Request $request, $id)
{
    // Check if user is authenticated
    if (!Auth::check()) {
        return response()->json(['message' => 'Unauthorized. Please log in to update reviews.'], 401);
    }

    // Validate request
    $validatedData = $request->validate([
        'rating' => 'sometimes|required|integer|min:1|max:5',
        'comment' => 'nullable|string|max:1000',
        'images' => 'nullable|array|max:5',          // New uploaded images
        'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
        'remove_images' => 'nullable|array',         // Images to remove
        'remove_images.*' => 'string',
    ]);

    $review = Review::find($id);
    if (!$review) {
        return response()->json(['message' => 'Review not found'], 404);
    }

    // Check ownership
    if ($review->user_id !== Auth::id()) {
        return response()->json(['message' => 'Unauthorized. You can only edit your own reviews.'], 403);
    }

    // Get existing images from columns
    $existingImages = array_filter([
        $review->image1,
        $review->image2,
        $review->image3,
        $review->image4,
        $review->image5,
    ]);

    // Remove requested images
    if (!empty($validatedData['remove_images'])) {
        foreach ($validatedData['remove_images'] as $removeImage) {
            if (($key = array_search($removeImage, $existingImages)) !== false) {
                unset($existingImages[$key]);
                Storage::disk('public')->delete($removeImage);
            }
        }
    }

    // Add new uploaded images
    if ($request->hasFile('images')) {
        foreach ($request->file('images') as $image) {
            $path = $image->store('reviews', 'public');
            $existingImages[] = $path;
        }
    }

    // Limit to max 5 images
    $existingImages = array_slice($existingImages, 0, 5);
    $existingImages = array_values($existingImages); // reindex

    // Prepare update data mapping to image1..image5 columns
    $updateData = [
        'rating' => $validatedData['rating'] ?? $review->rating,
        'comment' => $validatedData['comment'] ?? $review->comment,
    ];

    for ($i = 0; $i < 5; $i++) {
        $updateData['image'.($i+1)] = $existingImages[$i] ?? null;
    }

    // Update the review
    $review->update($updateData);

    return response()->json([
        'message' => 'Review updated successfully!',
        'review' => $review->load('user')
    ]);
}


    public function destroy($id)
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthorized. Please log in to delete reviews.'], 401);
        }

        $review = Review::find($id);
        if (!$review) {
            return response()->json(['message' => 'Review not found'], 404);
        }

        // Check if user owns the review or is admin
        if ($review->user_id !== Auth::id() && Auth::user()->role !== 'Admin') {
            return response()->json(['message' => 'Unauthorized. You can only delete your own reviews.'], 403);
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
            ->orderBy('created_at', 'desc')
            ->get();

        // Return empty array instead of 404 when no reviews found
        return response()->json($reviews);
    }

    public function getUserReviews()
    {
        // Check if user is authenticated - only logged in users can view their own reviews
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthorized. Please log in to view your reviews.'], 401);
        }

        // Get all reviews by the authenticated user
        $reviews = Review::with('user')
            ->where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($reviews);
    }
}