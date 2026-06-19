<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    // =========================
    // CREATE ORDER (CHECKOUT)
    // =========================
    public function store(Request $request)
    {
        $request->validate([
            'payment_method' => 'required|in:cod,card',
            'shipping_address' => 'required|array',
            'shipping_address.full_name' => 'required|string',
            'shipping_address.email' => 'required|email',
            'shipping_address.phone' => 'required|string',
            'shipping_address.address' => 'required|string',
            'shipping_address.city' => 'required|string',
            'shipping_address.country' => 'required|string',
            'shipping_address.postal_code' => 'required|string',
        ]);

        $user = $request->user();

        // Get user cart
        $cartItems = Cart::where('user_id', $user->id)->with('product')->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'message' => 'Your cart is empty'
            ], 422);
        }

        // Check stock and calculate total
        $total = 0;
        $orderItems = [];

        foreach ($cartItems as $item) {
            $product = $item->product;

            if ($product->stock < $item->quantity) {
                return response()->json([
                    'message' => "Insufficient stock for product: {$product->name}"
                ], 422);
            }

            $total += $product->price * $item->quantity;
            $orderItems[] = [
                'product_id' => $product->id,
                'quantity' => $item->quantity,
                'price' => $product->price,
            ];
        }

        DB::beginTransaction();

        try {
            // Create order
            $order = Order::create([
                'user_id' => $user->id,
                'order_number' => 'ORD-' . time() . '-' . strtoupper(substr(uniqid(), -4)),
                'total_price' => $total,
                'status' => 'pending',
                'payment_method' => $request->payment_method,
                'shipping_address' => $request->shipping_address,
                'notes' => $request->notes,
            ]);

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

            // Create payment
            Payment::create([
                'order_id' => $order->id,
                'amount' => $total,
                'currency' => 'MAD',
                'payment_method' => $request->payment_method,
                'status' => $request->payment_method === 'cod' ? 'pending' : 'paid',
                'paid_at' => $request->payment_method === 'card' ? now() : null,
            ]);

            // Clear cart
            $cartItems->each->delete();

            DB::commit();

            return response()->json([
                'message' => 'Order created successfully',
                'order' => $order->load('items.product', 'payment'),
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
    // GET USER ORDERS
    // =========================
    public function index(Request $request)
    {
        $orders = Order::with('items.product', 'payment')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json($orders);
    }

    // =========================
    // SHOW SINGLE ORDER
    // =========================
    public function show($id, Request $request)
    {
        $order = Order::with('items.product', 'payment')
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json($order);
    }

    // =========================
    // ADMIN: GET ALL ORDERS
    // =========================
    public function adminIndex()
    {
        $orders = Order::with(['user', 'items.product', 'payment'])
            ->latest()
            ->get();

        return response()->json($orders);
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
        $order->update(['status' => $request->status]);

        if ($request->status === 'delivered') {
            $order->payment()->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Order status updated successfully',
            'order' => $order->load('payment')
        ]);
    }
}
