<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    // =========================
    // CREATE PAYMENT
    // =========================
    public function store(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'payment_method' => 'required|in:cod,card',
        ]);

        $order = Order::findOrFail($request->order_id);

        // Check if payment already exists
        if ($order->payment) {
            return response()->json([
                'message' => 'Payment already exists for this order',
                'payment' => $order->payment
            ], 422);
        }

        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => $order->total_price,
            'currency' => 'MAD',
            'payment_method' => $request->payment_method,
            'status' => $request->payment_method === 'cod' ? 'pending' : 'paid',
            'paid_at' => $request->payment_method === 'card' ? now() : null,
        ]);

        // Update order status
        $order->update([
            'status' => $request->payment_method === 'cod' ? 'processing' : 'paid',
        ]);

        return response()->json([
            'message' => 'Payment created successfully',
            'payment' => $payment,
            'order' => $order
        ]);
    }

    // =========================
    // GET PAYMENT BY ORDER
    // =========================
    public function show($orderId)
    {
        $payment = Payment::where('order_id', $orderId)->firstOrFail();
        return response()->json($payment);
    }
}
