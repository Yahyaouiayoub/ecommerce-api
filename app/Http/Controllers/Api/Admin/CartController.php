<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AbandonedCartMail;
use App\Models\Cart;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class CartController extends Controller
{
    // =========================
    // GET ALL CARTS (ADMIN)
    // =========================
    public function index(Request $request)
    {
        $query = Cart::with(['product', 'user'])
            ->selectRaw('carts.*, COALESCE(carts.quantity * products.price, 0) as item_total')
            ->leftJoin('products', 'carts.product_id', '=', 'products.id');

        // Filter by status
        if ($request->has('status')) {
            $query->where('carts.status', $request->status);
        }

        // Search by user name/email or session_id
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('carts.session_id', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('carts.created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('carts.created_at', '<=', $request->date_to);
        }

        $carts = $query->latest('carts.updated_at')->get();

        // Group cart items by user/session to build per-cart views
        $grouped = [];
        foreach ($carts as $item) {
            $ownerKey = $item->user_id ? "user_{$item->user_id}" : "session_{$item->session_id}";

            if (!isset($grouped[$ownerKey])) {
                $grouped[$ownerKey] = [
                    'id' => $ownerKey,
                    'user_id' => $item->user_id,
                    'session_id' => $item->session_id,
                    'user' => $item->user,
                    'status' => $item->status,
                    'items' => [],
                    'item_count' => 0,
                    'total_value' => 0,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ];
            }

            $grouped[$ownerKey]['items'][] = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'product' => $item->product,
                'price' => $item->product->price ?? 0,
                'subtotal' => ($item->product->price ?? 0) * $item->quantity,
                'created_at' => $item->created_at,
            ];

            $grouped[$ownerKey]['item_count'] += $item->quantity;
            $grouped[$ownerKey]['total_value'] += ($item->product->price ?? 0) * $item->quantity;

            // Use the latest status among items
            if ($item->status === 'abandoned') {
                $grouped[$ownerKey]['status'] = 'abandoned';
            } elseif ($item->status === 'converted' && $grouped[$ownerKey]['status'] !== 'abandoned') {
                $grouped[$ownerKey]['status'] = 'converted';
            }
        }

        // Get user info for guest carts
        foreach ($grouped as &$cart) {
            if (!$cart['user'] && $cart['user_id']) {
                $cart['user'] = User::find($cart['user_id']);
            }
        }

        return response()->json(array_values($grouped));
    }

    // =========================
    // GET SINGLE CART DETAIL (ADMIN)
    // =========================
    public function show($ownerKey)
    {
        // ownerKey is "user_{id}" or "session_{sessionId}"
        $query = Cart::with(['product', 'user']);

        if (str_starts_with($ownerKey, 'user_')) {
            $userId = (int) str_replace('user_', '', $ownerKey);
            $query->where('user_id', $userId);
        } elseif (str_starts_with($ownerKey, 'session_')) {
            $sessionId = str_replace('session_', '', $ownerKey);
            $query->where('session_id', $sessionId);
        } else {
            return response()->json(['message' => 'Invalid cart identifier'], 400);
        }

        $items = $query->get();

        if ($items->isEmpty()) {
            return response()->json(['message' => 'Cart not found'], 404);
        }

        $user = null;
        $first = $items->first();
        if ($first->user_id) {
            $user = User::find($first->user_id);
        }

        $cartData = [
            'id' => $ownerKey,
            'user_id' => $first->user_id,
            'session_id' => $first->session_id,
            'user' => $user,
            'status' => $first->status,
            'items' => $items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'product' => $item->product,
                    'price' => $item->product->price ?? 0,
                    'subtotal' => ($item->product->price ?? 0) * $item->quantity,
                    'created_at' => $item->created_at,
                ];
            }),
            'item_count' => $items->sum('quantity'),
            'total_value' => $items->sum(function ($item) {
                return ($item->product->price ?? 0) * $item->quantity;
            }),
            'created_at' => $first->created_at,
            'updated_at' => $first->updated_at,
        ];

        return response()->json($cartData);
    }

    // =========================
    // MARK CART AS ABANDONED
    // =========================
    public function markAbandoned(Request $request, $ownerKey)
    {
        $query = Cart::query();

        if (str_starts_with($ownerKey, 'user_')) {
            $userId = (int) str_replace('user_', '', $ownerKey);
            $query->where('user_id', $userId);
        } elseif (str_starts_with($ownerKey, 'session_')) {
            $sessionId = str_replace('session_', '', $ownerKey);
            $query->where('session_id', $sessionId);
        } else {
            return response()->json(['message' => 'Invalid cart identifier'], 400);
        }

        // Don't allow marking converted carts as abandoned
        $cart = $query->first();
        if (!$cart) {
            return response()->json(['message' => 'Cart not found'], 404);
        }
        if ($cart->status === 'converted') {
            return response()->json(['message' => 'Cannot mark a converted cart as abandoned'], 422);
        }

        $query->update(['status' => 'abandoned']);

        // Send email notification if requested
        $sentInfo = '';
        if ($request->boolean('notify', false)) {
            $sentInfo = $this->sendAbandonedNotification($ownerKey);
        }

        return response()->json(['message' => 'Cart marked as abandoned' . $sentInfo]);
    }

    /**
     * Send abandoned cart email notification.
     */
    private function sendAbandonedNotification(string $ownerKey): string
    {
        try {
            if (str_starts_with($ownerKey, 'user_')) {
                $userId = (int) str_replace('user_', '', $ownerKey);
                $items = Cart::with('product')->where('user_id', $userId)->get();
                $cartOwner = User::find($userId);
            } elseif (str_starts_with($ownerKey, 'session_')) {
                $sessionId = str_replace('session_', '', $ownerKey);
                $items = Cart::with('product')->where('session_id', $sessionId)->get();
                $cartOwner = null;
            } else {
                return '';
            }

            if ($items->isEmpty()) {
                return '';
            }

            $mail = new AbandonedCartMail($ownerKey, $items, $cartOwner);
            $recipients = [];

            // Send reminder to the cart owner if they have an email
            if ($cartOwner && $cartOwner->email) {
                $recipients[] = $cartOwner->email;
                Mail::to($cartOwner->email)->send($mail);
            }

            // Also send notification to admin
            $adminEmail = config('mail.from.address');
            if ($adminEmail) {
                $recipients[] = $adminEmail;
                Mail::to($adminEmail)->send($mail);
            }

            if (!empty($recipients)) {
                return ' and notification sent to ' . implode(', ', array_unique($recipients));
            }

            return '';
        } catch (\Exception $e) {
            return ' but notification failed';
        }
    }

    // =========================
    // DELETE CART
    // =========================
    // =========================
    // DELETE CART
    // =========================
    public function destroy($ownerKey)
    {
        $query = Cart::query();

        if (str_starts_with($ownerKey, 'user_')) {
            $userId = (int) str_replace('user_', '', $ownerKey);
            $query->where('user_id', $userId);
        } elseif (str_starts_with($ownerKey, 'session_')) {
            $sessionId = str_replace('session_', '', $ownerKey);
            $query->where('session_id', $sessionId);
        } else {
            return response()->json(['message' => 'Invalid cart identifier'], 400);
        }

        $count = $query->count();
        if ($count === 0) {
            return response()->json(['message' => 'Cart not found'], 404);
        }

        $query->delete();

        return response()->json(['message' => "Cart deleted ({$count} items removed)"]);
    }

    // =========================
    // CONVERT GUEST CART TO USER CART
    // =========================
    public function convertToUser(Request $request, $ownerKey)
    {
        // Validate request
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $targetUserId = (int) $request->user_id;
        $targetUser = User::find($targetUserId);

        if (!$targetUser) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Only allow converting guest carts (identified by session_id)
        if (!str_starts_with($ownerKey, 'session_')) {
            return response()->json(['message' => 'Only guest carts (identified by session ID) can be converted to a user'], 422);
        }

        $sessionId = str_replace('session_', '', $ownerKey);

        // Get the guest cart items
        $guestItems = Cart::where('session_id', $sessionId)
            ->where('status', 'active')
            ->get();

        if ($guestItems->isEmpty()) {
            return response()->json(['message' => 'Guest cart not found'], 404);
        }

        // Check if the target user already has active cart items
        $existingUserItems = Cart::where('user_id', $targetUserId)
            ->where('status', 'active')
            ->get()
            ->keyBy('product_id');

        // Merge: update quantities for existing products, transfer new ones
        foreach ($guestItems as $guestItem) {
            if ($existingUserItems->has($guestItem->product_id)) {
                // Product already in user's cart — increment quantity
                $existingItem = $existingUserItems->get($guestItem->product_id);
                $existingItem->quantity += $guestItem->quantity;
                $existingItem->save();
                // Delete the guest cart item
                $guestItem->delete();
            } else {
                // Transfer the guest item to the user
                $guestItem->user_id = $targetUserId;
                $guestItem->session_id = null;
                $guestItem->save();
            }
        }

        // Fetch the updated cart for the user
        $updatedCart = Cart::with(['product', 'user'])
            ->where('user_id', $targetUserId)
            ->where('status', 'active')
            ->get();

        $cartData = [
            'id' => "user_{$targetUserId}",
            'user_id' => $targetUserId,
            'session_id' => null,
            'user' => $targetUser,
            'status' => 'active',
            'items' => $updatedCart->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'product' => $item->product,
                    'price' => $item->product->price ?? 0,
                    'subtotal' => ($item->product->price ?? 0) * $item->quantity,
                    'created_at' => $item->created_at,
                ];
            }),
            'item_count' => $updatedCart->sum('quantity'),
            'total_value' => $updatedCart->sum(function ($item) {
                return ($item->product->price ?? 0) * $item->quantity;
            }),
            'created_at' => $updatedCart->first()?->created_at,
            'updated_at' => now(),
        ];

        return response()->json([
            'message' => "Guest cart transferred to {$targetUser->full_name} successfully.",
            'cart' => $cartData,
        ]);
    }
}
