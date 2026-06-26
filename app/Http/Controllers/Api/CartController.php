<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CartController extends Controller
{
    // Helper to get session_id
    private function getSessionId(Request $request)
    {
        $sessionId = $request->header('X-Session-Id');

        if (!$sessionId) {
            $sessionId = Str::uuid()->toString();
        }

        return $sessionId;
    }

    // Get cart identifier (user_id or session_id)
    private function getCartIdentifier(Request $request)
    {
        $user = auth('sanctum')->user();

        if ($user) {
            return ['user_id' => $user->id, 'session_id' => null];
        }

        return ['user_id' => null, 'session_id' => $this->getSessionId($request)];
    }

    // =========================
    // GET CART
    // =========================
    public function index(Request $request)
    {
        $identifier = $this->getCartIdentifier($request);

        $cart = Cart::with('product', 'variant')
            ->where('status', 'active')
            ->when($identifier['user_id'], function ($query) use ($identifier) {
                return $query->where('user_id', $identifier['user_id']);
            })
            ->when($identifier['session_id'], function ($query) use ($identifier) {
                return $query->where('session_id', $identifier['session_id']);
            })
            ->get();

        $response = [
            'cart' => $cart,
            'session_id' => $identifier['session_id']
        ];

        return response()->json($response);
    }

    // =========================
    // ADD TO CART
    // =========================
    public function add(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'sometimes|integer|min:1',
            'variant_id' => 'nullable|exists:product_variants,id',
        ]);

        $identifier = $this->getCartIdentifier($request);

        if (!$identifier['user_id'] && !$identifier['session_id']) {
            return response()->json([
                'message' => 'Session ID required'
            ], 400);
        }

        $product = \App\Models\Product::findOrFail($request->product_id);

        // Validate variant belongs to this product
        $variant = null;
        if ($request->variant_id) {
            $variant = \App\Models\ProductVariant::where('id', $request->variant_id)
                ->where('product_id', $request->product_id)
                ->first();
            if (!$variant) {
                return response()->json([
                    'message' => 'Invalid variant for this product.'
                ], 422);
            }
        }

        // Check stock (use variant stock if variant selected, otherwise product stock)
        $stockCheck = $variant ? $variant->stock : $product->stock;
        if ($stockCheck <= 0) {
            $itemName = $variant ? $variant->name : $product->name;
            return response()->json([
                'message' => "{$itemName} is out of stock."
            ], 422);
        }

        // Find existing cart item matching same product + variant
        $cart = Cart::where('product_id', $request->product_id)
            ->where('variant_id', $request->variant_id)
            ->when($identifier['user_id'], function ($query) use ($identifier) {
                return $query->where('user_id', $identifier['user_id']);
            })
            ->when($identifier['session_id'], function ($query) use ($identifier) {
                return $query->where('session_id', $identifier['session_id']);
            })
            ->first();

        $newQuantity = $request->quantity ?? 1;
        if ($cart) {
            $newQuantity = $cart->quantity + $newQuantity;
        }

        if ($newQuantity > $stockCheck) {
            $maxStock = $stockCheck;
            return response()->json([
                'message' => "Only {$maxStock} unit(s) available."
            ], 422);
        }

        if ($cart) {
            $cart->quantity = $newQuantity;
            $cart->save();
        } else {
            $cart = Cart::create([
                'user_id' => $identifier['user_id'],
                'session_id' => $identifier['session_id'],
                'product_id' => $request->product_id,
                'variant_id' => $request->variant_id,
                'quantity' => $newQuantity,
            ]);
        }

        return response()->json([
            'message' => 'Item added to cart',
            'cart' => $cart,
            'session_id' => $identifier['session_id']
        ]);
    }

    // =========================
    // UPDATE CART ITEM
    // =========================
    public function update(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $identifier = $this->getCartIdentifier($request);

        $cart = Cart::with('product', 'variant')->where('id', $id)
            ->when($identifier['user_id'], function ($query) use ($identifier) {
                return $query->where('user_id', $identifier['user_id']);
            })
            ->when($identifier['session_id'], function ($query) use ($identifier) {
                return $query->where('session_id', $identifier['session_id']);
            })
            ->firstOrFail();

        $product = $cart->product;
        $stockCheck = $cart->variant ? $cart->variant->stock : ($product ? $product->stock : 0);

        if ($product && $request->quantity > $stockCheck) {
            return response()->json([
                'message' => "Only {$stockCheck} unit(s) available."
            ], 422);
        }

        $cart->update(['quantity' => $request->quantity]);

        return response()->json([
            'message' => 'Cart updated successfully',
            'cart' => $cart
        ]);
    }

    // =========================
    // REMOVE FROM CART
    // =========================
    public function remove(Request $request, $id)
    {
        $identifier = $this->getCartIdentifier($request);

        $cart = Cart::where('id', $id)
            ->when($identifier['user_id'], function ($query) use ($identifier) {
                return $query->where('user_id', $identifier['user_id']);
            })
            ->when($identifier['session_id'], function ($query) use ($identifier) {
                return $query->where('session_id', $identifier['session_id']);
            })
            ->firstOrFail();

        $cart->delete();

        return response()->json([
            'message' => 'Item removed from cart'
        ]);
    }

    // =========================
    // CLEAR CART
    // =========================
    public function clear(Request $request)
    {
        $identifier = $this->getCartIdentifier($request);

        Cart::when($identifier['user_id'], function ($query) use ($identifier) {
                return $query->where('user_id', $identifier['user_id']);
            })
            ->when($identifier['session_id'], function ($query) use ($identifier) {
                return $query->where('session_id', $identifier['session_id']);
            })
            ->delete();

        return response()->json([
            'message' => 'Cart cleared successfully'
        ]);
    }

    // =========================
    // MERGE CART (when user logs in)
    // =========================
    public function merge(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'User must be authenticated to merge cart'
            ], 401);
        }

        $guestCart = Cart::where('session_id', $request->session_id)->get();

        foreach ($guestCart as $item) {
            $userCart = Cart::where('user_id', $user->id)
                ->where('product_id', $item->product_id)
                ->where('variant_id', $item->variant_id)
                ->first();

            if ($userCart) {
                $userCart->quantity += $item->quantity;
                $userCart->save();
                $item->delete();
            } else {
                $item->update([
                    'user_id' => $user->id,
                    'session_id' => null
                ]);
            }
        }

        return response()->json([
            'message' => 'Cart merged successfully',
        ]);
    }
}
