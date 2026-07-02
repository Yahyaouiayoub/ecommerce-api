<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use App\Models\Product;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    /**
     * GET /wishlist
     * Return the authenticated user's wishlist with product details.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $perPage = (int) ($request->per_page ?? 20);
        $wishlist = Wishlist::with([
            'product' => function ($q) {
                $q->select('id', 'name', 'slug', 'price', 'discount_price', 'stock', 'thumbnail', 'category_id', 'is_active')
                    ->with([
                        'category' => function ($cat) {
                            $cat->select('id', 'name', 'slug');
                        },
                        'brand' => function ($b) {
                            $b->select('id', 'name', 'slug');
                        },
                        'images' => function ($img) {
                            $img->select('id', 'product_id', 'image_url', 'sort_order');
                        },
                    ]);
            },
        ])
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(min($perPage, 100));

        return response()->json([
            'wishlist' => $wishlist->items(),
            'total' => $wishlist->total(),
            'current_page' => $wishlist->currentPage(),
            'last_page' => $wishlist->lastPage(),
            'per_page' => $wishlist->perPage(),
        ]);
    }

    /**
     * POST /wishlist/{product}
     * Add a product to the user's wishlist.
     */
    public function store(Request $request, Product $product)
    {
        $user = $request->user();

        // Check if already wishlisted
        $existing = Wishlist::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Product is already in your wishlist.',
                'wishlist' => $existing->load('product'),
            ], 200);
        }

        $wishlist = Wishlist::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        $wishlist->load('product');

        return response()->json([
            'message' => 'Product added to wishlist.',
            'wishlist' => $wishlist,
        ], 201);
    }

    /**
     * DELETE /wishlist/{product}
     * Remove a product from the user's wishlist.
     */
    public function destroy(Request $request, Product $product)
    {
        $user = $request->user();

        $deleted = Wishlist::where('user_id', $user->id)
            ->where('product_id', $product->id)
            ->delete();

        if ($deleted === 0) {
            return response()->json([
                'message' => 'Product not found in your wishlist.',
            ], 404);
        }

        return response()->json([
            'message' => 'Product removed from wishlist.',
        ]);
    }
}
