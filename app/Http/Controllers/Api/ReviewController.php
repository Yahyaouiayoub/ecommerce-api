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
    public function index($productId)
    {
        $product = Product::findOrFail($productId);

        $reviews = Review::with('user:id,first_name,last_name,avatar')
            ->where('product_id', $productId)
            ->latest()
            ->get();

        return response()->json([
            'reviews' => $reviews,
            'average_rating' => round($product->reviews()->avg('rating') ?? 0, 1),
            'total_reviews' => $product->reviews()->count(),
            'rating_distribution' => [
                1 => $product->reviews()->where('rating', 1)->count(),
                2 => $product->reviews()->where('rating', 2)->count(),
                3 => $product->reviews()->where('rating', 3)->count(),
                4 => $product->reviews()->where('rating', 4)->count(),
                5 => $product->reviews()->where('rating', 5)->count(),
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
        ]);

        $review->load('user:id,first_name,last_name,avatar');

        return response()->json([
            'message' => 'Review submitted successfully',
            'review' => $review,
        ], 201);
    }
}
