<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\ReviewImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ReviewController extends Controller
{
    public function index()
    {
        $reviews = Review::with(['user', 'images'])->latest()->get();
        return response()->json($reviews);
    }

    public function show($id)
    {
        $review = Review::with(['user', 'images'])->find($id);
        
        if (!$review) {
            return response()->json(['message' => 'Review not found'], 404);
        }
        
        return response()->json($review);
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
            'reviewImage' => 'nullable|array|max:5',
            'reviewImage.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        DB::beginTransaction();
        try {
            // Check if user has already reviewed this entity
            $existingReview = Review::where('user_id', Auth::id())
                ->where('entity_type', $validatedData['entity_type'])
                ->where('entity_id', $validatedData['entity_id'])
                ->first();

            if ($existingReview) {
                return response()->json(['message' => 'You have already reviewed this entity.'], 400);
            }

            $review = Review::create([
                'user_id' => Auth::id(),
                'entity_type' => $validatedData['entity_type'],
                'entity_id' => $validatedData['entity_id'],
                'rating' => $validatedData['rating'],
                'comment' => $validatedData['comment'] ?? null,
            ]);

            // Handle image uploads
            if ($request->hasFile('reviewImage')) {
                $this->processImages($review, $request->file('reviewImage'));
            }

            DB::commit();

            $review->load(['user', 'images']);

            return response()->json([
                'message' => 'Review created successfully!',
                'review' => $review
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Review creation failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create review', 
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthorized. Please log in to update reviews.'], 401);
        }

        $validatedData = $request->validate([
            'rating' => 'sometimes|required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'reviewImage' => 'nullable|array|max:5',
            'reviewImage.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'removedImages' => 'nullable|array',
            'removedImages.*' => 'integer|exists:review_images,id',
        ]);

        $review = Review::with('images')->find($id);
        if (!$review) {
            return response()->json(['message' => 'Review not found'], 404);
        }

        // Check ownership
        if ($review->user_id !== Auth::id() && Auth::user()->role !== 'Admin') {
            return response()->json(['message' => 'Unauthorized. You can only edit your own reviews.'], 403);
        }

        DB::beginTransaction();
        try {
            // Handle removed images
            if (isset($validatedData['removedImages']) && !empty($validatedData['removedImages'])) {
                $imagesToRemove = ReviewImage::where('review_id', $review->id)
                    ->whereIn('id', $validatedData['removedImages'])
                    ->get();

                foreach ($imagesToRemove as $image) {
                    if (Storage::disk('public')->exists($image->image_path)) {
                        Storage::disk('public')->delete($image->image_path);
                    }
                    $image->delete();
                }
            }

            // Handle new image uploads
            if ($request->hasFile('reviewImage')) {
                $this->processImages($review, $request->file('reviewImage'));
            }

            // Update review fields
            $review->update([
                'rating' => $validatedData['rating'] ?? $review->rating,
                'comment' => $validatedData['comment'] ?? $review->comment,
            ]);

            // Reorder images
            $this->reorderImages($review->id);

            DB::commit();

            // Reload with relationships
            $review->load(['user', 'images']);

            return response()->json([
                'message' => 'Review updated successfully!',
                'review' => $review
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Review update failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update review',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthorized. Please log in to delete reviews.'], 401);
        }

        $review = Review::with('images')->find($id);
        if (!$review) {
            return response()->json(['message' => 'Review not found'], 404);
        }

        // Check if user owns the review or is admin
        if ($review->user_id !== Auth::id() && Auth::user()->role !== 'Admin') {
            return response()->json(['message' => 'Unauthorized. You can only delete your own reviews.'], 403);
        }

        DB::beginTransaction();
        try {
            // Delete associated images
            foreach ($review->images as $image) {
                if (Storage::disk('public')->exists($image->image_path)) {
                    Storage::disk('public')->delete($image->image_path);
                }
                $image->delete();
            }

            $review->delete();

            DB::commit();

            return response()->json(['message' => 'Review deleted successfully!']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Review deletion failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to delete review',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getReviewsByEntity($entityType, $entityId)
    {
        if (!in_array($entityType, ['location', 'hotel', 'guide', 'shop', 'vehicle'])) {
            return response()->json(['message' => 'Invalid entity type'], 400);
        }

        $reviews = Review::with(['user', 'images'])
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($reviews);
    }

    public function getUserReviews()
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthorized. Please log in to view your reviews.'], 401);
        }

        $reviews = Review::with(['user', 'images'])
            ->where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($reviews);
    }

    /**
     * Process and store review images
     */
    private function processImages($review, $images)
    {
        foreach ($images as $index => $image) {
            // Generate unique filename
            $filename = 'review_' . $review->id . '_' . time() . '_' . $index . '.' . $image->getClientOriginalExtension();
            
            // Create directory path
            $directory = 'reviews/' . $review->id;
            
            // Store the image
            $path = $image->storeAs($directory, $filename, 'public');

            // Create image record
            ReviewImage::create([
                'review_id' => $review->id,
                'image_path' => $path,
                'alt_text' => 'Review image ' . ($index + 1),
                'order_index' => $index
            ]);
        }
    }

    /**
     * Reorder images after deletions
     */
    private function reorderImages($reviewId)
    {
        $images = ReviewImage::where('review_id', $reviewId)
            ->orderBy('order_index')
            ->get();

        foreach ($images as $newIndex => $image) {
            $image->update(['order_index' => $newIndex]);
        }
    }
}