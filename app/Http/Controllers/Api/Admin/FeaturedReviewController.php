<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeaturedReviewController extends Controller
{
    /**
     * Get all reviews with optional filters (admin management view).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Review::with([
            'user:id,first_name,last_name,email,avatar',
            'product:id,name,slug,thumbnail',
        ]);

        // Filter by featured status
        if ($request->filled('featured')) {
            $query->where('is_featured', $request->featured === 'true' || $request->featured === '1');
        }

        // Filter by rating
        if ($request->filled('rating')) {
            $query->where('rating', (int) $request->rating);
        }

        // Filter by product
        if ($request->filled('product_id')) {
            $query->where('product_id', (int) $request->product_id);
        }

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->user_id);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        // Search across comment, user name, product name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('comment', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($uq) => $uq->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%"))
                  ->orWhereHas('product', fn($pq) => $pq->where('name', 'like', "%{$search}%"));
            });
        }

        // Sort: featured first, then by date
        $sortField = $request->input('sort', 'created_at');
        $sortDir = $request->input('dir', 'desc');

        if (in_array($sortField, ['created_at', 'rating', 'featured_order', 'is_featured'])) {
            $query->orderBy($sortField, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->latest();
        }

        $reviews = $query->paginate($request->input('per_page', 20));

        // Transform to include computed fields
        $reviews->getCollection()->transform(function ($review) {
            return $this->formatReview($review);
        });

        return response()->json($reviews);
    }

    /**
     * Get a single review by ID.
     */
    public function show(int $id): JsonResponse
    {
        $review = Review::with([
            'user:id,first_name,last_name,email,avatar',
            'product:id,name,slug,thumbnail,price',
            'order:id,order_number,status',
        ])->findOrFail($id);

        return response()->json([
            'data' => $this->formatReview($review),
        ]);
    }

    /**
     * Mark a review as featured (or unmark it).
     */
    public function toggleFeatured(int $id): JsonResponse
    {
        $review = Review::findOrFail($id);
        $review->update([
            'is_featured' => !$review->is_featured,
            // When newly featured, set featured_order to max+1
            'featured_order' => !$review->is_featured
                ? $review->featured_order
                : (Review::where('is_featured', true)->max('featured_order') ?? 0) + 1,
        ]);

        $review->refresh();

        return response()->json([
            'message' => $review->is_featured
                ? 'Review marked as featured.'
                : 'Review unmarked as featured.',
            'data'    => $this->formatReview($review),
        ]);
    }

    /**
     * Toggle whether the featured review is active (visible on homepage).
     */
    public function toggleActive(int $id): JsonResponse
    {
        $review = Review::findOrFail($id);
        if (!$review->is_featured) {
            return response()->json([
                'message' => 'Only featured reviews can be toggled.',
            ], 422);
        }

        $review->update(['is_featured_active' => !$review->is_featured_active]);
        $review->refresh();

        return response()->json([
            'message' => $review->is_featured_active
                ? 'Featured review activated.'
                : 'Featured review deactivated.',
            'data'    => $this->formatReview($review),
        ]);
    }

    /**
     * Update the featured display order.
     */
    public function updateOrder(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'featured_order' => 'required|integer|min:0|max:9999',
        ]);

        $review = Review::findOrFail($id);

        if (!$review->is_featured) {
            return response()->json([
                'message' => 'Only featured reviews can have order updated.',
            ], 422);
        }

        $review->update(['featured_order' => $request->featured_order]);

        return response()->json([
            'message' => 'Featured review order updated.',
            'data'    => $this->formatReview($review->fresh()),
        ]);
    }

    /**
     * Batch reorder featured reviews.
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'items'   => 'required|array|min:1',
            'items.*' => 'required|array:featured_order,id',
            'items.*.id'             => 'required|integer|exists:reviews,id',
            'items.*.featured_order' => 'required|integer|min:0|max:9999',
        ]);

        foreach ($request->items as $item) {
            Review::where('id', $item['id'])
                ->where('is_featured', true)
                ->update(['featured_order' => $item['featured_order']]);
        }

        return response()->json([
            'message' => 'Featured reviews reordered successfully.',
        ]);
    }

    /**
     * Get products with review counts for the filter dropdown.
     */
    public function products(): JsonResponse
    {
        $products = Product::whereHas('reviews')
            ->withCount('reviews')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return response()->json([
            'data' => $products,
        ]);
    }

    /**
     * Get featured review dashboard stats.
     */
    public function stats(): JsonResponse
    {
        $totalReviews = Review::count();
        $featuredReviews = Review::where('is_featured', true)->count();
        $activeFeatured = Review::featured()->count();
        $avgRatingFeatured = (float) Review::featured()->avg('rating') ?? 0;

        return response()->json([
            'data' => compact('totalReviews', 'featuredReviews', 'activeFeatured', 'avgRatingFeatured'),
        ]);
    }

    /**
     * Format a review for the API response.
     */
    private function formatReview($review): array
    {
        return [
            'id'                  => $review->id,
            'product_id'          => $review->product_id,
            'user_id'             => $review->user_id,
            'order_id'            => $review->order_id,
            'rating'              => $review->rating,
            'comment'             => $review->comment,
            'is_featured'         => $review->is_featured,
            'is_featured_active'  => $review->is_featured_active,
            'featured_order'      => $review->featured_order,
            'is_verified_purchase' => $review->is_verified_purchase,
            'created_at'          => $review->created_at?->toIso8601String(),
            'updated_at'          => $review->updated_at?->toIso8601String(),
            'user'                => $review->user ? [
                'id'         => $review->user->id,
                'first_name' => $review->user->first_name,
                'last_name'  => $review->user->last_name,
                'email'      => $review->user->email,
                'avatar'     => $review->user->avatar,
                'full_name'  => $review->user->full_name,
            ] : null,
            'product'             => $review->product ? [
                'id'        => $review->product->id,
                'name'      => $review->product->name,
                'slug'      => $review->product->slug,
                'thumbnail' => $review->product->thumbnail,
            ] : null,
        ];
    }
}
