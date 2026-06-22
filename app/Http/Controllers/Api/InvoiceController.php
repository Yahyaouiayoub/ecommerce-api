<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PaymentResource;
use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class InvoiceController extends Controller
{
    // =========================
    // VALID STATUS TRANSITIONS
    // =========================
    private const VALID_TRANSITIONS = [
        'pending'         => ['unpaid', 'cancelled'],
        'unpaid'          => ['partially_paid', 'paid', 'failed', 'cancelled'],
        'partially_paid'  => ['paid', 'failed', 'refunded'],
        'paid'            => ['refunded'],
        'failed'          => ['unpaid', 'pending'],
        'refunded'        => [],  // terminal
        'cancelled'       => [],  // terminal
    ];

    /**
     * Validate that a status transition is allowed.
     */
    private function isValidTransition(string $from, string $to): bool
    {
        return in_array($to, self::VALID_TRANSITIONS[$from] ?? [], true);
    }

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
        $invoice = Invoice::with('order.items.product', 'order.address', 'order.user', 'payments')->findOrFail($id);

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

        $order = Order::with('address', 'user')->findOrFail($request->order_id);

        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $alreadyInvoiced = $order->invoices()->sum('total_amount');
        $remainingOrderTotal = (float) $order->total_price - (float) $alreadyInvoiced;

        if ($request->total_amount > $remainingOrderTotal) {
            return response()->json([
                'message' => "Cannot invoice more than the remaining order total. Remaining: " . number_format($remainingOrderTotal, 2) . " MAD",
            ], 422);
        }

        // Determine billing info from order
        $billingName = null;
        $billingEmail = null;
        $billingPhone = null;
        $billingAddress = null;

        if ($order->address) {
            $billingName = $order->address->full_name ?? ($order->user?->full_name ?? $order->guest_name);
            $billingEmail = $order->address->email ?? $order->user?->email ?? $order->guest_email;
            $billingPhone = $order->address->phone;
            $billingAddress = $order->address->full_address ?? implode(', ', array_filter([
                $order->address->address_line1,
                $order->address->address_line2,
                $order->address->city,
                $order->address->state,
                $order->address->postal_code,
                $order->address->country,
            ]));
        } else {
            $billingName = $order->user?->full_name ?? $order->guest_name;
            $billingEmail = $order->user?->email ?? $order->guest_email;
        }

        $invoice = Invoice::create([
            'order_id'        => $order->id,
            'invoice_number'  => Invoice::generateInvoiceNumber(),
            'total_amount'    => $request->total_amount,
            'paid_amount'     => 0,
            'status'          => 'unpaid',
            'due_date'        => $request->due_date,
            'notes'           => $request->notes,
            'billing_name'    => $billingName,
            'billing_email'   => $billingEmail,
            'billing_phone'   => $billingPhone,
            'billing_address' => $billingAddress,
            'payment_method'  => $order->payment_method,
            'issued_at'       => now(),
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
            if ((float) $request->total_amount < (float) $invoice->paid_amount) {
                return response()->json([
                    'message' => 'Total amount cannot be less than the already paid amount (' . $invoice->paid_formatted . ').',
                ], 422);
            }
            $data['total_amount'] = $request->total_amount;
        }

        $invoice->update($data);

        if ($request->has('total_amount')) {
            $invoice->recalculateStatus()->save();
        }

        return response()->json([
            'message' => 'Invoice updated successfully',
            'data'    => new InvoiceResource($invoice->fresh()->load('order')),
        ]);
    }

    // =========================
    // UPDATE INVOICE STATUS (admin only)
    // =========================
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:unpaid,partially_paid,paid,pending,failed,refunded,cancelled',
        ]);

        $invoice = Invoice::findOrFail($id);

        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $newStatus = $request->status;

        // Validate transition
        if (!$this->isValidTransition($invoice->status, $newStatus)) {
            $allowed = implode(', ', self::VALID_TRANSITIONS[$invoice->status] ?? ['none']);
            return response()->json([
                'message' => "Invalid status transition from '{$invoice->status}'. Allowed: {$allowed}."
            ], 422);
        }

        // Perform side effects based on status
        if ($newStatus === 'refunded') {
            $invoice->markAsRefunded();
        } elseif ($newStatus === 'cancelled') {
            $invoice->markAsCancelled();
        } elseif ($newStatus === 'failed') {
            $invoice->markAsFailed();
        } elseif (in_array($newStatus, ['paid', 'partially_paid', 'unpaid', 'pending'])) {
            if ($newStatus === 'paid') {
                // When marking as paid, sync the paid_amount and let recalculateStatus
                // set the proper status and paid_at
                $invoice->paid_amount = $invoice->total_amount;
                $invoice->recalculateStatus()->save();
            } else {
                $invoice->update(['status' => $newStatus]);
            }
        } else {
            $invoice->update(['status' => $newStatus]);
            $invoice->recalculateStatus()->save();
        }

        return response()->json([
            'message' => 'Invoice status updated successfully',
            'data'    => new InvoiceResource($invoice->fresh()->load('order', 'payments')),
        ]);
    }

    // =========================
    // SEND INVOICE PDF TO CUSTOMER EMAIL (admin only)
    // =========================
    public function sendPdf($id, Request $request)
    {
        $invoice = Invoice::with(['order.items.product', 'order.address', 'order.user', 'payments'])->findOrFail($id);

        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $recipientEmail = $invoice->billing_email ?? $invoice->order?->user?->email;

        if (!$recipientEmail) {
            return response()->json([
                'message' => 'No billing email found for this invoice.',
            ], 422);
        }

        try {
            Mail::to($recipientEmail)->send(new InvoiceMail($invoice));

            return response()->json([
                'message' => "Invoice sent successfully to {$recipientEmail}.",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send invoice: ' . $e->getMessage(),
            ], 500);
        }
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
    // ADMIN: LIST ALL INVOICES (with full filtering)
    // =========================
    public function adminIndex(Request $request)
    {
        $query = Invoice::with(['order.user', 'order.items.product', 'payments']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by payment method
        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        // Filter by order
        if ($request->has('order_id')) {
            $query->where('order_id', $request->order_id);
        }

        // Search (invoice number, order number, customer name/email)
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('order', function ($oq) use ($search) {
                      $oq->where('order_number', 'like', "%{$search}%")
                         ->orWhereHas('user', function ($uq) use ($search) {
                             $uq->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                         });
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

        // Filter by paid_at date range
        if ($request->has('paid_from')) {
            $query->whereDate('paid_at', '>=', $request->paid_from);
        }
        if ($request->has('paid_to')) {
            $query->whereDate('paid_at', '<=', $request->paid_to);
        }

        $invoices = $query->latest()->paginate($request->per_page ?? 20);

        return response()->json([
            'data' => InvoiceResource::collection($invoices->items()),
            'current_page' => $invoices->currentPage(),
            'last_page' => $invoices->lastPage(),
            'per_page' => $invoices->perPage(),
            'total' => $invoices->total(),
        ]);
    }

    // =========================
    // ADMIN: VIEW INVOICE DETAIL
    // =========================
    public function adminShow($id)
    {
        $invoice = Invoice::with([
            'order.items.product',
            'order.address',
            'order.user',
            'payments',
        ])->findOrFail($id);

        // Get order totals breakdown
        $subtotal = 0;
        foreach ($invoice->order->items as $item) {
            $subtotal += $item->price * $item->quantity;
        }

        // Try to get shipping and tax from order or calculate
        $shipping = 0;
        $tax = 0;
        $totalOrderPrice = (float) $invoice->order->total_price;

        if ($subtotal > 0 && $totalOrderPrice > $subtotal) {
            $difference = $totalOrderPrice - $subtotal;
            // Rough estimate: tax first, then shipping
            $taxSettings = Setting::getTaxSettings();
            if ($taxSettings['enabled']) {
                if ($taxSettings['type'] === 'percentage') {
                    $tax = round($subtotal * ($taxSettings['rate'] / 100), 2);
                    $shipping = round($difference - $tax, 2);
                } else {
                    $tax = round($taxSettings['rate'], 2);
                    $shipping = round($difference - $tax, 2);
                }
            } else {
                $shipping = $difference;
            }
        }

        return response()->json([
            'data' => new InvoiceResource($invoice),
            'meta' => [
                'subtotal' => round($subtotal, 2),
                'shipping' => round($shipping, 2),
                'tax'      => round($tax, 2),
                'total'    => $totalOrderPrice,
            ],
        ]);
    }

    // =========================
    // ADMIN: ORDER INVOICE SUMMARY
    // =========================
    public function orderSummary($orderId, Request $request)
    {
        $order = Order::with('invoices.payments')->findOrFail($orderId);

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

    // =========================
    // INVOICE STATISTICS
    // =========================
    public function stats()
    {
        $totalInvoices = Invoice::count();
        $paidInvoices = Invoice::where('status', 'paid')->count();
        $pendingInvoices = Invoice::whereIn('status', ['unpaid', 'partially_paid', 'pending'])->count();
        $refundedInvoices = Invoice::where('status', 'refunded')->count();
        $failedInvoices = Invoice::where('status', 'failed')->count();
        $cancelledInvoices = Invoice::where('status', 'cancelled')->count();
        $totalRevenue = (float) Invoice::where('status', 'paid')->sum('paid_amount');
        $totalPendingAmount = (float) Invoice::whereIn('status', ['unpaid', 'partially_paid', 'pending'])
            ->get()
            ->sum(function ($inv) {
                return $inv->remaining_amount;
            });

        return response()->json([
            'total_invoices'       => $totalInvoices,
            'paid_invoices'        => $paidInvoices,
            'pending_invoices'     => $pendingInvoices,
            'refunded_invoices'    => $refundedInvoices,
            'failed_invoices'      => $failedInvoices,
            'cancelled_invoices'   => $cancelledInvoices,
            'total_revenue'        => $totalRevenue,
            'total_pending_amount' => $totalPendingAmount,
        ]);
    }

    // =========================
    // INVOICE PDF GENERATION
    // =========================

    /**
     * Preview invoice PDF in browser.
     */
    public function previewPdf($id, Request $request)
    {
        return $this->generatePdfResponse($id, $request, 'stream');
    }

    /**
     * Download invoice PDF.
     */
    public function downloadPdf($id, Request $request)
    {
        return $this->generatePdfResponse($id, $request, 'download');
    }

    /**
     * Generate and return the PDF response.
     * Accepts auth via Bearer token or token query parameter for direct browser access.
     */
    private function generatePdfResponse($id, Request $request, string $mode)
    {
        $invoice = Invoice::with([
            'order.items.product',
            'order.address',
            'order.user',
            'payments',
        ])->findOrFail($id);

        // Auth: check Bearer header, logged-in session, or ?token= query param (for direct browser access)
        $user = $request->user(); // auth:sanctum compatible

        if (!$user) {
            // Try Bearer token from Authorization header (for routes without auth:sanctum middleware)
            $bearerToken = $request->bearerToken();
            if ($bearerToken) {
                $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($bearerToken);
                if ($personalAccessToken) {
                    $user = $personalAccessToken->tokenable;
                    \Illuminate\Support\Facades\Auth::setUser($user);
                }
            }
        }

        if (!$user && $request->has('token')) {
            // Try token from query parameter (for browser-initiated downloads)
            $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($request->token);
            if ($personalAccessToken) {
                $user = $personalAccessToken->tokenable;
                \Illuminate\Support\Facades\Auth::setUser($user);
            }
        }

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Check authorization
        $order = $invoice->order;
        $isAdmin = $user->isAdmin();
        $isOwner = $order && $order->user_id === $user->id;

        if (!$isAdmin && !$isOwner) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Get subtotal from order items
        $subtotal = 0;
        foreach ($invoice->order->items as $item) {
            $subtotal += $item->price * $item->quantity;
        }

        // Calculate shipping and tax
        $shipping = 0;
        $tax = 0;
        $totalOrderPrice = (float) $invoice->order->total_price;

        if ($subtotal > 0 && $totalOrderPrice > $subtotal) {
            $difference = $totalOrderPrice - $subtotal;
            $taxSettings = Setting::getTaxSettings();
            if ($taxSettings['enabled']) {
                if ($taxSettings['type'] === 'percentage') {
                    $tax = round($subtotal * ($taxSettings['rate'] / 100), 2);
                    $shipping = round($difference - $tax, 2);
                } else {
                    $tax = round($taxSettings['rate'], 2);
                    $shipping = round($difference - $tax, 2);
                }
            } else {
                $shipping = $difference;
            }
        }

        $settings = [
            'company_name' => Setting::getValue('company_name', 'Lumen Store'),
            'company_address' => Setting::getValue('company_address', '123 Commerce Street'),
            'company_city' => Setting::getValue('company_city', 'Casablanca'),
            'company_country' => Setting::getValue('company_country', 'Morocco'),
            'company_phone' => Setting::getValue('company_phone', '+212 5XX-XXXXXX'),
            'company_email' => Setting::getValue('company_email', 'contact@lumenstore.com'),
        ];

        $data = [
            'invoice'            => $invoice,
            'order'              => $invoice->order,
            'items'              => $invoice->order->items,
            'subtotal'           => $subtotal,
            'shipping'           => $shipping,
            'tax'                => $tax,
            'payments'           => $invoice->payments,
            'settings'           => (object) $settings,
        ];

        $pdf = Pdf::loadView('pdfs.invoice', $data);
        $pdf->setPaper('A4', 'portrait');

        $filename = 'invoice-' . $invoice->invoice_number . '.pdf';

        if ($mode === 'download') {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }
}
