<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Refund;
use App\Services\RefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RefundController extends Controller
{
    public function __construct(
        private RefundService $refundService
    ) {}

    /**
     * POST /refunds
     * Create a refund request.
     */
    public function store(Request $request)
    {
        $user = auth('sanctum')->user();

        $request->validate([
            'order_id'         => 'required|integer|exists:orders,id',
            'reason'           => 'required|string|max:255',
            'description'      => 'nullable|string|max:2000',
            'items'            => 'required|array|min:1',
            'items.*.order_item_id' => 'required|integer|exists:order_items,id',
            'items.*.quantity'      => 'required|integer|min:1',
            'images'           => 'nullable|array|max:5',
            'images.*'         => 'image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB
        ]);

        $order = Order::with('items')->findOrFail($request->order_id);

        // Verify ownership
        if ($user) {
            if ($order->user_id !== $user->id) {
                return response()->json(['message' => 'You do not own this order.'], 403);
            }
        } else {
            // Guest: verify via session or guest email
            $sessionId = $this->getSessionId($request);
            if (!$sessionId && !$request->guest_email) {
                return response()->json(['message' => 'Authentication required.'], 401);
            }
            if ($sessionId && $order->session_id !== $sessionId) {
                return response()->json(['message' => 'You do not own this order.'], 403);
            }
        }

        // Verify order is eligible for refund
        if (!in_array($order->status, ['delivered', 'shipped', 'processing'])) {
            return response()->json([
                'message' => 'This order is not eligible for a refund. Only delivered, shipped, or processing orders can be refunded.'
            ], 422);
        }

        // Calculate total refund amount for requested items
        $totalAmount = 0;
        foreach ($request->items as $item) {
            $orderItem = $order->items->firstWhere('id', $item['order_item_id']);
            if (!$orderItem) {
                return response()->json([
                    'message' => "Order item #{$item['order_item_id']} not found in this order."
                ], 422);
            }
            if ($item['quantity'] > $orderItem->quantity) {
                return response()->json([
                    'message' => "Requested quantity ({$item['quantity']}) exceeds ordered quantity ({$orderItem->quantity}) for item #{$item['order_item_id']}."
                ], 422);
            }
            $totalAmount += $orderItem->price * $item['quantity'];
        }

        // Check max refundable amount
        $maxRefundable = $this->refundService->getMaxRefundableAmount($order);
        if ($totalAmount > $maxRefundable) {
            return response()->json([
                'message' => "Refund amount exceeds the maximum refundable amount ({$maxRefundable} MAD). Already refunded: " . ($order->total_price - $maxRefundable) . " MAD."
            ], 422);
        }

        $images = $request->hasFile('images') ? $request->file('images') : [];

        $refund = $this->refundService->createRefund(
            $order,
            $user,
            $request->reason,
            $request->description,
            $request->items,
            round($totalAmount, 2),
            $images,
            $request->guest_email,
            $request->guest_name,
        );

        return response()->json([
            'message' => 'Refund request submitted successfully.',
            'refund'  => $refund->load(['items.orderItem.product', 'images', 'order']),
        ], 201);
    }

    /**
     * GET /refunds
     * List refund requests for the current user.
     */
    public function index(Request $request)
    {
        $user = auth('sanctum')->user();
        $query = Refund::with(['items.orderItem.product', 'order', 'images']);

        if ($user) {
            $query->where('user_id', $user->id);
        } else {
            $sessionId = $this->getSessionId($request);
            if ($sessionId) {
                $query->whereHas('order', fn($q) => $q->where('session_id', $sessionId));
            } else {
                return response()->json([]);
            }
        }

        $refunds = $query->latest()->get();
        return response()->json($refunds);
    }

    /**
     * GET /refunds/{id}
     * Show a single refund request.
     */
    public function show($id, Request $request)
    {
        $user = auth('sanctum')->user();
        $refund = Refund::with(['items.orderItem.product', 'images', 'order'])->findOrFail($id);

        // Authorization
        if ($user) {
            if ($refund->user_id !== $user->id) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
        } else {
            $sessionId = $this->getSessionId($request);
            if (!$sessionId || $refund->order->session_id !== $sessionId) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
        }

        return response()->json($refund);
    }

    /**
     * GET /orders/{id}/refundable-items
     * Get refundable items for an order.
     */
    public function refundableItems($orderId, Request $request)
    {
        $user = auth('sanctum')->user();
        $order = Order::with('items.product')->findOrFail($orderId);

        // Verify ownership
        if ($user) {
            if ($order->user_id !== $user->id) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
        } else {
            $sessionId = $this->getSessionId($request);
            if (!$sessionId || $order->session_id !== $sessionId) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
        }

        $items = $this->refundService->getRefundableItems($order);
        $maxRefundable = $this->refundService->getMaxRefundableAmount($order);

        return response()->json([
            'items'           => $items,
            'max_refundable'  => $maxRefundable,
            'order_total'     => (float) $order->total_price,
            'already_refunded' => (float) ($order->refund_amount ?? 0),
        ]);
    }

    /**
     * Get the session ID from the request header.
     */
    private function getSessionId(Request $request): ?string
    {
        return $request->header('X-Session-Id');
    }
}
