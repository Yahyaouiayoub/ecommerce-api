<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    // =========================
    // GET REVIEWS FOR A PRODUCT
    // =========================
    public function index($productId, Request $request)
    {
        $product = Product::findOrFail($productId);

        // Paginate reviews (page of reviews for display)
        $perPage = (int) ($request->per_page ?? 10);
        $reviews = Review::approved()
            ->with('user:id,first_name,last_name,avatar')
            ->where('product_id', $productId)
            ->latest()
            ->paginate(min($perPage, 50));

        // Compute rating distribution from the entire review set (single aggregate query)
        $ratingDistribution = Review::approved()
            ->where('product_id', $productId)
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating');

        return response()->json([
            'reviews' => $reviews->items(),
            'current_page' => $reviews->currentPage(),
            'last_page' => $reviews->lastPage(),
            'per_page' => $reviews->perPage(),
            'total_reviews' => $reviews->total(),
            'average_rating' => round(
                Review::approved()->where('product_id', $productId)->avg('rating') ?? 0, 1
            ),
            'rating_distribution' => [
                1 => (int) ($ratingDistribution[1] ?? 0),
                2 => (int) ($ratingDistribution[2] ?? 0),
                3 => (int) ($ratingDistribution[3] ?? 0),
                4 => (int) ($ratingDistribution[4] ?? 0),
                5 => (int) ($ratingDistribution[5] ?? 0),
            ],
        ]);
    }

    // =========================
    // SUBMIT A REVIEW (authenticated users only)
    // =========================
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'order_id' => 'required|exists:orders,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();

        // Verify the order belongs to this user
        $order = Order::where('id', $request->order_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'Order not found or does not belong to you.',
            ], 403);
        }

        // Only delivered orders can be reviewed
        if ($order->status !== 'delivered') {
            return response()->json([
                'message' => 'You can only review products from delivered orders.',
            ], 422);
        }

        // Verify the product is in this order
        $orderItem = OrderItem::where('order_id', $order->id)
            ->where('product_id', $request->product_id)
            ->first();

        if (!$orderItem) {
            return response()->json([
                'message' => 'This product is not in the specified order.',
            ], 422);
        }

        // Check if already reviewed
        $existing = Review::where('product_id', $request->product_id)
            ->where('user_id', $user->id)
            ->where('order_id', $order->id)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'You have already reviewed this product for this order.',
            ], 422);
        }

        $review = Review::create([
            'product_id' => $request->product_id,
            'user_id' => $user->id,
            'order_id' => $order->id,
            'rating' => $request->rating,
            'comment' => $request->comment,
            'status'   => 'pending',
        ]);

        $review->load('user:id,first_name,last_name,avatar');

        return response()->json([
            'message' => 'Review submitted successfully',
            'review' => $review,
        ], 201);
    }
}
