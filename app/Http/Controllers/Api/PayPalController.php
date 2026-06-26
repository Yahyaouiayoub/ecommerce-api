<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Cart;
use App\Services\PayPalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class PayPalController extends Controller
{
    private PayPalService $paypal;

    public function __construct()
    {
        $this->paypal = new PayPalService();
    }

    // =========================
    // TEST PAYPAL CONNECTION
    // =========================
    public function testConnection(Request $request)
    {
        $result = $this->paypal->testConnection();

        return response()->json($result);
    }

    // =========================
    // CREATE PAYPAL PAYMENT (after order is submitted)
    // =========================
    public function createPayment(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
        ]);

        $order = Order::with('items')->findOrFail($request->order_id);

        // Verify the order belongs to the authenticated user
        if (!$request->user() || $order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (!$this->paypal->isConfigured()) {
            return response()->json([
                'message' => 'PayPal is not configured. Please contact support.',
            ], 422);
        }

        $appUrl = config('app.url', 'http://localhost:8000');
        $currency = config('app.currency', 'MAD');
        $returnUrl = $appUrl . '/api/paypal/return?order_id=' . $order->id;
        $cancelUrl = $appUrl . '/api/paypal/cancel?order_id=' . $order->id;

        $result = $this->paypal->createOrder(
            amount: (float) $order->total_price,
            currency: $currency,
            returnUrl: $returnUrl,
            cancelUrl: $cancelUrl,
            referenceId: (string) $order->id,
        );

        if ($result['success']) {
            // Store PayPal order ID temporarily on the order
            $order->update([
                'payment_method' => 'paypal',
            ]);

            return response()->json([
                'message' => 'PayPal payment created.',
                'data' => [
                    'paypal_order_id' => $result['data']['paypal_order_id'],
                    'approval_url' => $result['data']['approval_url'],
                ],
            ]);
        }

        return response()->json([
            'message' => $result['message'],
        ], 422);
    }

    // =========================
    // RETURN URL (after PayPal approval)
    // =========================
    public function returnCallback(Request $request)
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
        $paypalOrderId = $request->token;
        $orderId = $request->order_id;

        if (!$paypalOrderId || !$orderId) {
            return redirect()->to($frontendUrl . '/checkout?paypal=error&reason=missing_params');
        }

        $order = Order::find($orderId);

        if (!$order) {
            return redirect()->to($frontendUrl . '/checkout?paypal=error&reason=order_not_found');
        }

        // Capture the PayPal payment
        $result = $this->paypal->captureOrder($paypalOrderId);

        if ($result['success']) {
            $transactionId = $result['data']['transaction_id'] ?? '';

            DB::beginTransaction();
            try {
                // Update order status (remains 'pending', payment is already handled)
                $order->update([
                    'payment_method' => 'paypal',
                ]);

                // Update invoice as paid
                $invoice = Invoice::where('order_id', $order->id)->first();
                if ($invoice) {
                    $invoice->paid_amount = (float) $order->total_price;
                    $invoice->recalculateStatus(); // sets status to 'paid' and paid_at
                    $invoice->save();

                    // Create payment record
                    Payment::create([
                        'order_id'       => $order->id,
                        'invoice_id'     => $invoice->id,
                        'amount'         => (float) $order->total_price,
                        'currency'       => 'USD',
                        'payment_method' => 'paypal',
                        'payment_type'   => 'full',
                        'status'         => 'paid',
                        'transaction_id' => $transactionId ?: $paypalOrderId,
                        'paid_at'        => now(),
                    ]);
                }

                // Clear cart
                if ($order->user_id) {
                    Cart::where('user_id', $order->user_id)
                        ->where('status', 'active')
                        ->update(['status' => 'converted']);
                }

                DB::commit();

                return redirect()->to($frontendUrl . "/order-confirmation/{$order->id}?paypal=success");
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('PayPal capture DB error', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
                return redirect()->to($frontendUrl . "/order-confirmation/{$order->id}?paypal=error");
            }
        }

        return redirect()->to($frontendUrl . "/checkout?paypal=error&reason={$result['message']}");
    }

    // =========================
    // CANCEL URL (user cancelled on PayPal)
    // =========================
    public function cancelCallback(Request $request)
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
        $orderId = $request->order_id;

        return redirect()->to($frontendUrl . ($orderId ? "/checkout?paypal=cancelled" : "/checkout"));
    }

    // =========================
    // GET PAYPAL SETTINGS (for admin)
    // =========================
    public function getSettings()
    {
        $settings = \App\Models\Setting::getPayPalSettings();

        // Mask the secret for display
        $settings['client_secret'] = $settings['client_secret']
            ? substr($settings['client_secret'], 0, 8) . '...' . substr($settings['client_secret'], -4)
            : '';

        return response()->json($settings);
    }

    // =========================
    // SAVE PAYPAL SETTINGS (admin)
    // =========================
    public function saveSettings(Request $request)
    {
        $request->validate([
            'enabled'       => 'required|boolean',
            'mode'          => 'required|in:sandbox,live',
            'client_id'     => 'required|string',
            'client_secret' => 'required|string',
            'webhook_id'    => 'nullable|string',
        ]);

        \App\Models\Setting::setValue('paypal_enabled', $request->enabled ? '1' : '0');
        \App\Models\Setting::setValue('paypal_mode', $request->mode);
        \App\Models\Setting::setValue('paypal_client_id', $request->client_id);
        \App\Models\Setting::setValue('paypal_client_secret', $request->client_secret);
        \App\Models\Setting::setValue('paypal_webhook_id', $request->webhook_id ?? '');

        // Clear cached PayPal service token
        $this->paypal = new PayPalService();

        return response()->json([
            'message' => 'PayPal settings saved successfully.',
        ]);
    }
}
