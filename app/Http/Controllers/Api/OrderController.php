<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Payment;
use App\Models\Revenue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    // =========================
    // VALID STATUS TRANSITIONS
    // =========================
    private const VALID_TRANSITIONS = [
        'pending'    => ['processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped'    => ['delivered', 'cancelled'],
        'delivered'  => [],  // terminal
        'cancelled'  => [],  // terminal
    ];

    /**
     * Validate that a status transition is allowed.
     */
    private function isValidTransition(string $from, string $to): bool
    {
        return in_array($to, self::VALID_TRANSITIONS[$from] ?? [], true);
    }

    // =========================
    // HELPERS
    // =========================
    private function getSessionId(Request $request): ?string
    {
        return $request->header('X-Session-Id');
    }

    /**
     * Get cart items for the current request (authenticated user or guest session).
     */
    private function getCartItems(Request $request)
    {
        $user = auth('sanctum')->user();

        if ($user) {
            return Cart::where('user_id', $user->id)->with('product')->get();
        }

        $sessionId = $this->getSessionId($request);
        if ($sessionId) {
            return Cart::where('session_id', $sessionId)->with('product')->get();
        }

        return collect();
    }

    /**
     * Get the cart identifier for clearing cart after order placement.
     */
    private function getCartIdentifier(Request $request): array
    {
        $user = auth('sanctum')->user();

        if ($user) {
            return ['user_id' => $user->id, 'session_id' => null];
        }

        return ['user_id' => null, 'session_id' => $this->getSessionId($request)];
    }

    // =========================
    // CALCULATE ORDER TOTALS (consistent with frontend OrderSummary component)
    // =========================
    private function calculateOrderTotals(float $subtotal, ?int $shippingMethodId = null): array
    {
        $taxSettings = \App\Models\Setting::getTaxSettings();

        // Calculate shipping from the selected method
        $shipping = 0;
        $shippingMethodName = null;
        $shippingMethod = null;

        if ($shippingMethodId) {
            $shippingMethod = \App\Models\ShippingMethod::find($shippingMethodId);
        }

        // Fallback to default active shipping method
        if (!$shippingMethod) {
            $shippingMethod = \App\Models\ShippingMethod::getActive()->first();
        }

        if ($shippingMethod && $subtotal > 0) {
            $shipping = $shippingMethod->getEffectiveCost($subtotal);
            $shippingMethodName = $shippingMethod->name;
        }

        // Calculate tax
        $tax = 0;
        if ($taxSettings['enabled']) {
            if ($taxSettings['type'] === 'percentage') {
                $tax = round($subtotal * ($taxSettings['rate'] / 100), 2);
            } else {
                // Fixed amount
                $tax = round($taxSettings['rate'], 2);
            }
        }

        $total = round($subtotal + $shipping + $tax, 2);

        return [
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'tax' => $tax,
            'total' => $total,
            'shipping_method_name' => $shippingMethodName,
        ];
    }

    // =========================
    // CREATE ORDER (CHECKOUT)
    // =========================
    public function store(Request $request)
    {
        $user = auth('sanctum')->user();

        $request->validate([
            'payment_method' => 'required|in:cod,card',
            'shipping_method_id' => 'sometimes|nullable|integer|exists:shipping_methods,id',
            'notes' => 'nullable|string',
        ]);

        if ($user) {
            // Authenticated user checkout
            $request->validate([
                'address_id' => 'required|integer|exists:addresses,id',
            ]);

            // Verify the address belongs to the user
            $address = \App\Models\Address::where('user_id', $user->id)->findOrFail($request->address_id);
        } else {
            // Guest checkout
            $request->validate([
                'guest_name' => 'required|string|max:255',
                'guest_email' => 'required|email|max:255',
                'guest_phone' => 'nullable|string|max:20',
                'address_line1' => 'required|string|max:255',
                'address_line2' => 'nullable|string|max:255',
                'city' => 'required|string|max:255',
                'state' => 'nullable|string|max:255',
                'postal_code' => 'nullable|string|max:20',
                'country' => 'required|string|max:255',
            ]);
        }

        // Get cart items
        $cartItems = $this->getCartItems($request);

        if ($cartItems->isEmpty()) {
            return response()->json([
                'message' => 'Your cart is empty'
            ], 422);
        }

        // Check stock and calculate subtotal
        $subtotal = 0;
        $orderItems = [];

        foreach ($cartItems as $item) {
            $product = $item->product;

            if (!$product) continue;

            if ($product->stock < $item->quantity) {
                return response()->json([
                    'message' => "Insufficient stock for product: {$product->name}"
                ], 422);
            }

            $subtotal += $product->price * $item->quantity;
            $orderItems[] = [
                'product_id' => $product->id,
                'quantity' => $item->quantity,
                'price' => $product->price,
            ];
        }

        // Calculate full totals (subtotal + shipping + tax) consistent with frontend
        $totals = $this->calculateOrderTotals($subtotal, $request->shipping_method_id);

        $identifier = $this->getCartIdentifier($request);

        DB::beginTransaction();

        try {
            $orderData = [
                'user_id' => $user ? $user->id : null,
                'session_id' => $identifier['session_id'],
                'order_number' => 'ORD-' . time() . '-' . strtoupper(substr(uniqid(), -4)),
                'total_price' => $totals['total'],
                'status' => 'pending',
                'payment_method' => $request->payment_method,
                'shipping_method_id' => $request->shipping_method_id,
                'notes' => $request->notes,
            ];

            if ($user) {
                // Authenticated: link to existing address
                $orderData['address_id'] = $request->address_id;
            } else {
                // Guest: store guest info and create an address record
                $orderData['guest_name'] = $request->guest_name;
                $orderData['guest_email'] = $request->guest_email;

                $address = \App\Models\Address::create([
                    'user_id' => null,
                    'full_name' => $request->guest_name,
                    'email' => $request->guest_email,
                    'phone' => $request->guest_phone,
                    'address_line1' => $request->address_line1,
                    'address_line2' => $request->address_line2,
                    'city' => $request->city,
                    'state' => $request->state,
                    'postal_code' => $request->postal_code,
                    'country' => $request->country,
                    'is_default' => false,
                ]);

                $orderData['address_id'] = $address->id;
            }

            // Create order
            $order = Order::create($orderData);

            // Create order items
            foreach ($orderItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                ]);

                // Reduce stock
                Product::find($item['product_id'])->decrement('stock', $item['quantity']);
            }

            // Create invoice for the order
            $invoice = \App\Models\Invoice::create([
                'order_id'       => $order->id,
                'invoice_number' => \App\Models\Invoice::generateInvoiceNumber(),
                'total_amount'   => $totals['total'],
                'paid_amount'    => $request->payment_method === 'card' ? $totals['total'] : 0,
                'status'         => $request->payment_method === 'card' ? 'paid' : 'unpaid',
                'issued_at'      => now(),
                'paid_at'        => $request->payment_method === 'card' ? now() : null,
            ]);

            if ($request->payment_method === 'card') {
                Payment::create([
                    'order_id'       => $order->id,
                    'invoice_id'     => $invoice->id,
                    'amount'         => $totals['total'],
                    'currency'       => 'MAD',
                    'payment_method' => 'card',
                    'payment_type'   => 'full',
                    'status'         => 'paid',
                    'paid_at'        => now(),
                ]);
            }

            // Mark cart as converted (instead of deleting)
            Cart::when($identifier['user_id'], function ($q) use ($identifier) {
                    return $q->where('user_id', $identifier['user_id']);
                })
                ->when($identifier['session_id'], function ($q) use ($identifier) {
                    return $q->where('session_id', $identifier['session_id']);
                })
                ->update(['status' => 'converted']);

            DB::commit();

            return response()->json([
                'message' => 'Order created successfully',
                'order' => $order->load('items.product', 'payment', 'invoices', 'address'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // =========================
    // GET USER ORDERS (authenticated user or guest by session)
    // =========================
    public function index(Request $request)
    {
        $user = auth('sanctum')->user();
        $query = Order::with('items.product', 'payment', 'invoices', 'address');

        if ($user) {
            $query->where('user_id', $user->id);
        } else {
            $sessionId = $this->getSessionId($request);
            if (!$sessionId) {
                return response()->json([]);
            }
            $query->where('session_id', $sessionId);
        }

        $orders = $query->latest()->get();
        return response()->json($orders);
    }

    // =========================
    // SHOW SINGLE ORDER
    // =========================
    public function show($id, Request $request)
    {
        $user = auth('sanctum')->user();
        $order = Order::with('items.product', 'payment', 'invoices', 'address');

        if ($user) {
            $order->where('user_id', $user->id);
        } else {
            $sessionId = $this->getSessionId($request);
            if ($sessionId) {
                $order->where('session_id', $sessionId);
            }
        }

        $order = $order->findOrFail($id);
        return response()->json($order);
    }

    // =========================
    // ADMIN: GET ALL ORDERS (with optional filters)
    // =========================
    public function adminIndex(Request $request)
    {
        $query = Order::with(['user', 'items.product', 'payment', 'invoices', 'address']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search by customer name, email, or order number
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $orders = $query->latest()->get();

        return response()->json($orders);
    }

    // =========================
    // ADMIN: GET SINGLE ORDER DETAIL
    // =========================
    public function adminShow($id)
    {
        $order = Order::with(['user', 'items.product', 'payment', 'invoices', 'address'])
            ->findOrFail($id);

        return response()->json($order);
    }

    // =========================
    // ADMIN: UPDATE ORDER STATUS
    // =========================
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,processing,shipped,delivered,cancelled',
        ]);

        $order = Order::findOrFail($id);
        $newStatus = $request->status;

        // Validate status transition
        if (!$this->isValidTransition($order->status, $newStatus)) {
            $allowed = implode(', ', self::VALID_TRANSITIONS[$order->status] ?? ['none']);
            return response()->json([
                'message' => "Invalid status transition from '{$order->status}'. Allowed transitions: {$allowed}."
            ], 422);
        }

        $order->update(['status' => $newStatus]);

        if ($newStatus === 'delivered') {
            // Create revenue record (order delivered generates revenue)
            Revenue::firstOrCreate(
                ['order_id' => $order->id],
                [
                    'amount' => $order->total_price,
                    'source' => 'order',
                    'reference' => $order->order_number,
                    'note' => 'Revenue from order ' . $order->order_number,
                    'revenue_date' => now(),
                ]
            );
        }

        // For card payments, mark the payment as paid
        if ($newStatus === 'delivered' && $order->payment_method === 'card') {
            $order->payment()->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
        }

        // If cancelled, restore stock
        if ($newStatus === 'cancelled') {
            foreach ($order->items as $item) {
                Product::where('id', $item->product_id)->increment('stock', $item->quantity);
            }
        }

        return response()->json([
            'message' => 'Order status updated successfully',
            'order' => $order->load('payment', 'revenue', 'invoices', 'address')
        ]);
    }
}
