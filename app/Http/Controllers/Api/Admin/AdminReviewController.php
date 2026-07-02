<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminReviewController extends Controller
{
    // =========================
    // LIST ALL REVIEWS (paginated, filterable)
    // =========================
    public function index(Request $request): JsonResponse
    {
        $query = Review::with([
            'user:id,first_name,last_name,email,avatar',
            'product:id,name,slug,thumbnail',
        ]);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by rating
        if ($request->filled('rating')) {
            $query->where('rating', $request->rating);
        }

        // Filter by product
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Filter by featured status
        if ($request->filled('featured')) {
            if ($request->featured === 'true' || $request->featured === '1') {
                $query->where('is_featured', true);
            } elseif ($request->featured === 'false' || $request->featured === '0') {
                $query->where(function ($q) {
                    $q->where('is_featured', false)->orWhereNull('is_featured');
                });
            }
        }

        // Date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search across comment, user name, product name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('comment', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where(DB::raw("CONCAT(first_name, ' ', last_name)"), 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  })
                  ->orWhereHas('product', function ($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Default sort: newest first
        $query->latest();

        $perPage = min((int) $request->per_page, 100) ?: 20;
        $reviews = $query->paginate($perPage);

        return response()->json($reviews);
    }

    // =========================
    // SHOW SINGLE REVIEW
    // =========================
    public function show(int $id): JsonResponse
    {
        $review = Review::with([
            'user:id,first_name,last_name,email,avatar',
            'product:id,name,slug,thumbnail,description',
            'order:id,order_number,status',
        ])->findOrFail($id);

        return response()->json([
            'data' => $review,
        ]);
    }

    // =========================
    // APPROVE REVIEW
    // =========================
    public function approve(int $id): JsonResponse
    {
        $review = Review::findOrFail($id);
        $review->update(['status' => 'approved']);

        return response()->json([
            'message' => 'Review approved successfully.',
            'data'    => $review->load('user:id,first_name,last_name,avatar', 'product:id,name,slug,thumbnail'),
        ]);
    }

    // =========================
    // REJECT REVIEW
    // =========================
    public function reject(int $id): JsonResponse
    {
        $review = Review::findOrFail($id);
        $review->update(['status' => 'rejected']);

        return response()->json([
            'message' => 'Review rejected successfully.',
            'data'    => $review->load('user:id,first_name,last_name,avatar', 'product:id,name,slug,thumbnail'),
        ]);
    }

    // =========================
    // PENDING REVIEW (set back to pending)
    // =========================
    public function pending(int $id): JsonResponse
    {
        $review = Review::findOrFail($id);
        $review->update(['status' => 'pending']);

        return response()->json([
            'message' => 'Review moved back to pending.',
            'data'    => $review->load('user:id,first_name,last_name,avatar', 'product:id,name,slug,thumbnail'),
        ]);
    }

    // =========================
    // BULK APPROVE
    // =========================
    public function bulkApprove(Request $request): JsonResponse
    {
        $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'integer|exists:reviews,id',
        ]);

        $count = Review::whereIn('id', $request->ids)->update(['status' => 'approved']);

        return response()->json([
            'message' => "{$count} review(s) approved successfully.",
            'count'   => $count,
        ]);
    }

    // =========================
    // BULK REJECT
    // =========================
    public function bulkReject(Request $request): JsonResponse
    {
        $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'integer|exists:reviews,id',
        ]);

        $count = Review::whereIn('id', $request->ids)->update(['status' => 'rejected']);

        return response()->json([
            'message' => "{$count} review(s) rejected successfully.",
            'count'   => $count,
        ]);
    }

    // =========================
    // STATS / SUMMARY
    // =========================
    public function stats(): JsonResponse
    {
        $total = Review::count();
        $pending = Review::where('status', 'pending')->count();
        $approved = Review::where('status', 'approved')->count();
        $rejected = Review::where('status', 'rejected')->count();

        $avgRating = Review::where('status', 'approved')->avg('rating');

        return response()->json([
            'data' => [
                'total_reviews'    => $total,
                'pending_reviews'  => $pending,
                'approved_reviews' => $approved,
                'rejected_reviews' => $rejected,
                'avg_rating'       => round($avgRating ?: 0, 1),
            ],
        ]);
    }

    // =========================
    // PRODUCTS WITH REVIEWS (for filter dropdown)
    // =========================
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
}
