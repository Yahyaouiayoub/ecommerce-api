<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    // =========================
    // LIST INVOICES (for an order or all)
    // =========================
    public function index(Request $request)
    {
        $query = Invoice::with('order');

        // Filter by order if specified
        if ($request->has('order_id')) {
            $query->where('order_id', $request->order_id);
        }

        // Filter by status if specified
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // If user is not admin, only show their own invoices
        if (!$request->user()->isAdmin()) {
            $query->whereHas('order', function ($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            });
        }

        $invoices = $query->latest()->get();

        return response()->json([
            'data' => InvoiceResource::collection($invoices),
        ]);
    }

    // =========================
    // SHOW A SINGLE INVOICE
    // =========================
    public function show($id, Request $request)
    {
        $invoice = Invoice::with('order')->findOrFail($id);

        // Ensure user owns the invoice (unless admin)
        if (!$request->user()->isAdmin() && $invoice->order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json([
            'data' => new InvoiceResource($invoice),
        ]);
    }

    // =========================
    // STORE (Create a new invoice for an order)
    // =========================
    public function store(Request $request)
    {
        $request->validate([
            'order_id'   => 'required|exists:orders,id',
            'total_amount' => 'required|numeric|min:0.01',
            'due_date'   => 'nullable|date',
            'notes'      => 'nullable|string',
        ]);

        $order = Order::findOrFail($request->order_id);

        // Only admin can create invoices for any order
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Calculate the total already invoiced for this order
        $alreadyInvoiced = $order->invoices()->sum('total_amount');
        $remainingOrderTotal = (float) $order->total_price - (float) $alreadyInvoiced;

        if ($request->total_amount > $remainingOrderTotal) {
            return response()->json([
                'message' => "Cannot invoice more than the remaining order total. Remaining: " . number_format($remainingOrderTotal, 2) . " MAD",
            ], 422);
        }

        $invoice = Invoice::create([
            'order_id'       => $order->id,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'total_amount'   => $request->total_amount,
            'paid_amount'    => 0,
            'status'         => 'unpaid',
            'due_date'       => $request->due_date,
            'notes'          => $request->notes,
            'issued_at'      => now(),
        ]);

        return response()->json([
            'message' => 'Invoice created successfully',
            'data'    => new InvoiceResource($invoice),
        ], 201);
    }

    // =========================
    // REGISTER A PAYMENT AGAINST AN INVOICE
    // =========================
    public function pay(Request $request, $id)
    {
        $request->validate([
            'amount'       => 'required|numeric|min:0.01',
            'payment_type' => 'sometimes|in:full,partial_50,partial_30,custom',
        ]);

        $invoice = Invoice::findOrFail($id);

        // Only admin can register payments
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($invoice->isPaid()) {
            return response()->json([
                'message' => 'This invoice is already fully paid',
            ], 422);
        }

        $amount = (float) $request->amount;
        $remaining = $invoice->remaining_amount;

        if ($amount > $remaining) {
            return response()->json([
                'message' => "Payment amount ({$amount}) exceeds the remaining balance ({$remaining} MAD).",
            ], 422);
        }

        $paymentType = $request->payment_type ?? 'full';

        // Register payment — creates Payment record and updates invoice
        $payment = $invoice->registerPayment(
            amount: $amount,
            paymentMethod: 'cod',
            paymentType: $paymentType
        );

        return response()->json([
            'message' => 'Payment registered successfully',
            'data'    => [
                'payment' => new PaymentResource($payment),
                'invoice' => new InvoiceResource($invoice->fresh()->load('order', 'payments')),
            ],
        ]);
    }

    // =========================
    // UPDATE INVOICE DETAILS (admin only)
    // =========================
    public function update(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);

        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'due_date'     => 'nullable|date',
            'notes'        => 'nullable|string',
            'total_amount' => 'nullable|numeric|min:0.01',
        ]);

        $data = $request->only(['due_date', 'notes']);

        if ($request->has('total_amount')) {
            // Ensure the new total is >= paid_amount
            if ((float) $request->total_amount < (float) $invoice->paid_amount) {
                return response()->json([
                    'message' => 'Total amount cannot be less than the already paid amount (' . $invoice->paid_formatted . ').',
                ], 422);
            }

            $data['total_amount'] = $request->total_amount;
        }

        $invoice->update($data);

        // Recalculate status if total_amount changed
        if ($request->has('total_amount')) {
            $invoice->recalculateStatus()->save();
        }

        return response()->json([
            'message' => 'Invoice updated successfully',
            'data'    => new InvoiceResource($invoice->fresh()->load('order')),
        ]);
    }

    // =========================
    // CANCEL / DELETE INVOICE (admin only)
    // =========================
    public function destroy($id, Request $request)
    {
        $invoice = Invoice::findOrFail($id);

        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($invoice->paid_amount > 0) {
            return response()->json([
                'message' => 'Cannot delete an invoice that has payments recorded against it.',
            ], 422);
        }

        $invoice->delete();

        return response()->json([
            'message' => 'Invoice deleted successfully',
        ]);
    }

    // =========================
    // ADMIN: LIST ALL INVOICES
    // =========================
    public function adminIndex(Request $request)
    {
        $query = Invoice::with('order.user');

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('order_id')) {
            $query->where('order_id', $request->order_id);
        }
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $invoices = $query->latest()->paginate($request->per_page ?? 20);

        return response()->json($invoices);
    }

    // =========================
    // ADMIN: VIEW ORDER INVOICE SUMMARY
    // =========================
    public function orderSummary($orderId, Request $request)
    {
        $order = Order::with('invoices')->findOrFail($orderId);

        $totalInvoiced = $order->invoices()->sum('total_amount');
        $totalPaid     = $order->invoices()->sum('paid_amount');

        return response()->json([
            'order_id'          => $order->id,
            'order_number'      => $order->order_number,
            'order_total'       => (float) $order->total_price,
            'total_invoiced'    => (float) $totalInvoiced,
            'total_paid'        => (float) $totalPaid,
            'remaining_to_pay'  => max(0, (float) $order->total_price - (float) $totalPaid),
            'invoice_count'     => $order->invoices()->count(),
            'invoices'          => InvoiceResource::collection($order->invoices),
        ]);
    }
}
