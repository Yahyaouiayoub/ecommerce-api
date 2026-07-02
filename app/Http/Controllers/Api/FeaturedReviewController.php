<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\JsonResponse;

class FeaturedReviewController extends Controller
{
    /**
     * Get active featured reviews for the homepage.
     * Only returns reviews that are marked as featured and active,
     * sorted by featured_order then newest.
     */
    public function index(): JsonResponse
    {
        $reviews = Review::approved()
            ->featured()
            ->featuredSorted()
            ->with([
                'user:id,first_name,last_name,avatar',
                'product:id,name,slug,thumbnail',
            ])
            ->get()
            ->map(fn($review) => [
                'id'                   => $review->id,
                'rating'               => $review->rating,
                'comment'              => $review->comment,
                'is_verified_purchase' => $review->is_verified_purchase,
                'created_at'           => $review->created_at?->toIso8601String(),
                'user'                 => $review->user ? [
                    'id'         => $review->user->id,
                    'first_name' => $review->user->first_name,
                    'last_name'  => $review->user->last_name,
                    'avatar'     => $review->user->avatar,
                    'full_name'  => $review->user->full_name,
                ] : null,
                'product'              => $review->product ? [
                    'id'        => $review->product->id,
                    'name'      => $review->product->name,
                    'slug'      => $review->product->slug,
                    'thumbnail' => $review->product->thumbnail,
                ] : null,
            ]);

        return response()->json([
            'data' => $reviews,
        ]);
    }
}
