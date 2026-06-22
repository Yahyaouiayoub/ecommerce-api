<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    // =========================
    // PAYMENT TYPE OPTIONS
    // =========================
    private const PAYMENT_TYPES = [
        'full'       => 1.0,   // 100%
        'partial_20' => 0.2,   // 20%
        'partial_30' => 0.3,   // 30%
        'partial_50' => 0.5,   // 50%
        'partial_60' => 0.6,   // 60%
        'partial_70' => 0.7,   // 70%
        'partial_80' => 0.8,   // 80%
        'custom'     => null,  // user-defined
    ];

    /**
     * Calculate the payment amount based on type and remaining balance.
     */
    private function calculateAmount(string $type, float $remaining, ?float $customAmount = null): float
    {
        if ($type === 'custom') {
            return $customAmount ?? $remaining;
        }

        $percentage = self::PAYMENT_TYPES[$type] ?? 1.0;
        return round($remaining * $percentage, 2);
    }

    // =========================
    // RECORD A PAYMENT AGAINST AN INVOICE (COD / partial)
    // =========================
    public function store(Request $request)
    {
        $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'payment_type' => 'required|in:full,partial_20,partial_30,partial_50,partial_60,partial_70,partial_80,custom',
            'amount' => 'nullable|numeric|min:0.01', // required for custom
        ]);

        $invoice = Invoice::with('order')->findOrFail($request->invoice_id);

        // Only admin can record payments for COD/partial
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($invoice->isPaid()) {
            return response()->json([
                'message' => 'This invoice is already fully paid.',
            ], 422);
        }

        $remaining = $invoice->remaining_amount;
        $paymentType = $request->payment_type;

        // Calculate amount
        $amount = $this->calculateAmount($paymentType, $remaining, $request->amount);

        // Validate custom amount
        if ($paymentType === 'custom') {
            if (!$request->has('amount')) {
                return response()->json([
                    'message' => 'A custom amount is required when payment type is "custom".',
                ], 422);
            }
            if ($amount > $remaining) {
                return response()->json([
                    'message' => "Payment amount ({$amount}) exceeds the remaining balance ({$remaining}).",
                ], 422);
            }
            if ($amount <= 0) {
                return response()->json([
                    'message' => 'Payment amount must be greater than zero.',
                ], 422);
            }
        }

        // Register payment — this creates the Payment record and updates the invoice
        $payment = $invoice->registerPayment(
            amount: $amount,
            paymentMethod: 'cod',
            paymentType: $paymentType
        );

        // Also update the order's payment_method if still COD
        $invoice->order->update([
            'payment_method' => 'cod',
        ]);

        return response()->json([
            'message' => 'Payment recorded successfully.',
            'data'    => [
                'payment' => new PaymentResource($payment),
                'invoice' => new InvoiceResource($invoice->fresh()->load('payments')),
                'order_remaining' => $invoice->order->total_price - $invoice->order->invoices()->sum('paid_amount'),
            ],
        ], 201);
    }

    // =========================
    // LIST ALL PAYMENTS FOR AN INVOICE
    // =========================
    public function index(Request $request)
    {
        $query = Payment::with('invoice.order');

        if ($request->has('invoice_id')) {
            $query->where('invoice_id', $request->invoice_id);
        }

        if ($request->has('order_id')) {
            $query->where('order_id', $request->order_id);
        }

        if ($request->has('payment_type')) {
            $query->where('payment_type', $request->payment_type);
        }

        // Non-admin users can only see their own payments
        if (!$request->user()->isAdmin()) {
            $query->whereHas('invoice.order', function ($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            });
        }

        $payments = $query->latest()->get();

        return response()->json([
            'data' => PaymentResource::collection($payments),
        ]);
    }

    // =========================
    // SHOW A SINGLE PAYMENT (by payment ID)
    // =========================
    public function show($id, Request $request)
    {
        $payment = Payment::with('invoice.order')->findOrFail($id);

        // Authorize: only admin or the payment owner
        if (!$request->user()->isAdmin()) {
            $order = $payment->invoice->order ?? $payment->order;
            if ($order->user_id !== $request->user()->id) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        return response()->json([
            'data' => new PaymentResource($payment),
        ]);
    }

    // =========================
    // SHOW PAYMENTS BY ORDER (legacy order-based lookup)
    // =========================
    public function showByOrder($orderId, Request $request)
    {
        $order = Order::findOrFail($orderId);

        // Authorize: only admin or the order owner
        if (!$request->user()->isAdmin() && $order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $payments = Payment::with('invoice')
            ->where('order_id', $orderId)
            ->latest()
            ->get();

        return response()->json([
            'data' => PaymentResource::collection($payments),
        ]);
    }

    // =========================
    // GET PAYMENT OPTIONS FOR AN INVOICE
    // =========================
    public function options($invoiceId)
    {
        $invoice = Invoice::findOrFail($invoiceId);

        if ($invoice->isPaid()) {
            return response()->json([
                'message' => 'This invoice is already fully paid.',
                'options' => [],
            ]);
        }

        $remaining = $invoice->remaining_amount;

        $options = [
            'full' => [
                'label' => 'Full Payment (100%)',
                'amount' => $remaining,
                'amount_formatted' => number_format($remaining, 2) . ' MAD',
            ],
            'partial_20' => [
                'label' => 'Partial Payment (20%)',
                'amount' => round($remaining * 0.2, 2),
                'amount_formatted' => number_format(round($remaining * 0.2, 2), 2) . ' MAD',
            ],
            'partial_30' => [
                'label' => 'Partial Payment (30%)',
                'amount' => round($remaining * 0.3, 2),
                'amount_formatted' => number_format(round($remaining * 0.3, 2), 2) . ' MAD',
            ],
            'partial_50' => [
                'label' => 'Partial Payment (50%)',
                'amount' => round($remaining * 0.5, 2),
                'amount_formatted' => number_format(round($remaining * 0.5, 2), 2) . ' MAD',
            ],
            'partial_60' => [
                'label' => 'Partial Payment (60%)',
                'amount' => round($remaining * 0.6, 2),
                'amount_formatted' => number_format(round($remaining * 0.6, 2), 2) . ' MAD',
            ],
            'partial_70' => [
                'label' => 'Partial Payment (70%)',
                'amount' => round($remaining * 0.7, 2),
                'amount_formatted' => number_format(round($remaining * 0.7, 2), 2) . ' MAD',
            ],
            'partial_80' => [
                'label' => 'Partial Payment (80%)',
                'amount' => round($remaining * 0.8, 2),
                'amount_formatted' => number_format(round($remaining * 0.8, 2), 2) . ' MAD',
            ],
            'custom' => [
                'label' => 'Custom Amount',
                'amount' => null,
                'max' => $remaining,
                'max_formatted' => number_format($remaining, 2) . ' MAD',
            ],
        ];

        return response()->json([
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'remaining' => $remaining,
            'remaining_formatted' => number_format($remaining, 2) . ' MAD',
            'options' => $options,
        ]);
    }

    // =========================
    // ADMIN: LIST ALL PAYMENTS (paginated)
    // =========================
    public function adminIndex(Request $request)
    {
        $query = Payment::with('invoice.order.user');

        if ($request->has('invoice_id')) {
            $query->where('invoice_id', $request->invoice_id);
        }
        if ($request->has('order_id')) {
            $query->where('order_id', $request->order_id);
        }
        if ($request->has('payment_type')) {
            $query->where('payment_type', $request->payment_type);
        }
        if ($request->has('date_from')) {
            $query->whereDate('paid_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('paid_at', '<=', $request->date_to);
        }

        $payments = $query->latest()->paginate($request->per_page ?? 20);

        return response()->json($payments);
    }

    // =========================
    // ADMIN: GET PAYMENT SUMMARY FOR AN ORDER
    // =========================
    public function orderPaymentSummary($orderId)
    {
        $order = Order::with('invoices.payments')->findOrFail($orderId);

        $totalPaid = 0;
        $payments = collect();
        $invoiceDetails = [];

        foreach ($order->invoices as $invoice) {
            $totalPaid += $invoice->paid_amount;
            $payments = $payments->merge($invoice->payments);
            $invoiceDetails[] = [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'total_amount' => (float) $invoice->total_amount,
                'paid_amount' => (float) $invoice->paid_amount,
                'remaining_amount' => $invoice->remaining_amount,
                'status' => $invoice->status,
                'payment_count' => $invoice->payments->count(),
            ];
        }

        return response()->json([
            'order_id'          => $order->id,
            'order_number'      => $order->order_number,
            'order_total'       => (float) $order->total_price,
            'total_paid'        => (float) $totalPaid,
            'remaining_to_pay'  => max(0, (float) $order->total_price - (float) $totalPaid),
            'payment_count'     => $payments->count(),
            'invoices'          => $invoiceDetails,
            'payments'          => PaymentResource::collection($payments->sortByDesc('paid_at')),
        ]);
    }
}
