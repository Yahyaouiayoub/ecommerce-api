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

        $cart = Cart::with('product')
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
        ]);

        $identifier = $this->getCartIdentifier($request);

        if (!$identifier['user_id'] && !$identifier['session_id']) {
            return response()->json([
                'message' => 'Session ID required'
            ], 400);
        }

        $cart = Cart::where('product_id', $request->product_id)
            ->when($identifier['user_id'], function ($query) use ($identifier) {
                return $query->where('user_id', $identifier['user_id']);
            })
            ->when($identifier['session_id'], function ($query) use ($identifier) {
                return $query->where('session_id', $identifier['session_id']);
            })
            ->first();

        if ($cart) {
            $cart->quantity += $request->quantity ?? 1;
            $cart->save();
        } else {
            $cart = Cart::create([
                'user_id' => $identifier['user_id'],
                'session_id' => $identifier['session_id'],
                'product_id' => $request->product_id,
                'quantity' => $request->quantity ?? 1,
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

        $cart = Cart::where('id', $id)
            ->when($identifier['user_id'], function ($query) use ($identifier) {
                return $query->where('user_id', $identifier['user_id']);
            })
            ->when($identifier['session_id'], function ($query) use ($identifier) {
                return $query->where('session_id', $identifier['session_id']);
            })
            ->firstOrFail();

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
