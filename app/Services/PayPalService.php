<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalService
{
    private string $clientId;
    private string $clientSecret;
    private string $mode;
    private ?string $accessToken = null;
    private string $baseUrl;

    public function __construct()
    {
        $settings = Setting::getPayPalSettings();
        $this->clientId = $settings['client_id'] ?? '';
        $this->clientSecret = $settings['client_secret'] ?? '';
        $this->mode = $settings['mode'] ?? 'sandbox';
        $this->baseUrl = $this->mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    /**
     * Check if PayPal is properly configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->clientId) && !empty($this->clientSecret);
    }

    /**
     * Get an OAuth 2.0 access token from PayPal.
     */
    public function getAccessToken(): ?string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        try {
            $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
                ->asForm()
                ->post("{$this->baseUrl}/v1/oauth2/token", [
                    'grant_type' => 'client_credentials',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $this->accessToken = $data['access_token'] ?? null;
                return $this->accessToken;
            }

            Log::error('PayPal auth failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('PayPal auth exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Test the PayPal connection by attempting to get an access token.
     * Returns ['success' => true] or ['success' => false, 'message' => ...]
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'PayPal is not configured. Please set Client ID and Client Secret.',
            ];
        }

        $token = $this->getAccessToken();

        if ($token) {
            return [
                'success' => true,
                'message' => 'Connection successful! Mode: ' . strtoupper($this->mode) . '.',
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to authenticate with PayPal. Check your Client ID and Client Secret.',
        ];
    }

    /**
     * Create a PayPal order (checkout session).
     *
     * @param float $amount The order total
     * @param string $currency e.g. 'USD' or 'MAD'
     * @param string $returnUrl URL to redirect after approval
     * @param string $cancelUrl URL to redirect if user cancels
     * @param string $referenceId Internal order reference
     * @return array ['success' => bool, 'data' => [...], 'message' => string]
     */
    public function createOrder(
        float $amount,
        string $currency,
        string $returnUrl,
        string $cancelUrl,
        string $referenceId = ''
    ): array {
        $token = $this->getAccessToken();

        if (!$token) {
            return [
                'success' => false,
                'message' => 'Failed to authenticate with PayPal.',
            ];
        }

        try {
            $response = Http::withToken($token)
                ->withHeader('PayPal-Request-Id', uniqid('pp_', true))
                ->post("{$this->baseUrl}/v2/checkout/orders", [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [
                        [
                            'reference_id' => $referenceId ?: uniqid('order_'),
                            'amount' => [
                                'currency_code' => $currency,
                                'value' => number_format($amount, 2, '.', ''),
                            ],
                        ],
                    ],
                    'payment_source' => [
                        'paypal' => [
                            'experience_context' => [
                                'return_url' => $returnUrl,
                                'cancel_url' => $cancelUrl,
                                'user_action' => 'PAY_NOW',
                            ],
                        ],
                    ],
                ]);

            if ($response->successful()) {
                $data = $response->json();

                // Find the approval URL
                $approvalUrl = '';
                foreach ($data['links'] ?? [] as $link) {
                    if ($link['rel'] === 'payer-action') {
                        $approvalUrl = $link['href'];
                        break;
                    }
                }

                return [
                    'success' => true,
                    'data' => [
                        'paypal_order_id' => $data['id'],
                        'status' => $data['status'],
                        'approval_url' => $approvalUrl,
                    ],
                ];
            }

            Log::error('PayPal create order failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => $response->json('message') ?? 'Failed to create PayPal order.',
                'details' => $response->json('details'),
            ];
        } catch (\Exception $e) {
            Log::error('PayPal create order exception: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while creating the PayPal order.',
            ];
        }
    }

    /**
     * Capture a PayPal order after user approval.
     *
     * @param string $paypalOrderId The PayPal Order ID returned after approval
     * @return array ['success' => bool, 'data' => [...], 'message' => string]
     */
    public function captureOrder(string $paypalOrderId): array
    {
        $token = $this->getAccessToken();

        if (!$token) {
            return [
                'success' => false,
                'message' => 'Failed to authenticate with PayPal.',
            ];
        }

        try {
            $response = Http::withToken($token)
                ->withHeader('PayPal-Request-Id', uniqid('pp_cap_', true))
                ->post("{$this->baseUrl}/v2/checkout/orders/{$paypalOrderId}/capture");

            if ($response->successful()) {
                $data = $response->json();

                $captureStatus = $data['status'] ?? '';
                $isCompleted = $captureStatus === 'COMPLETED';

                // Extract transaction ID from captures
                $transactionId = '';
                foreach ($data['purchase_units'] ?? [] as $unit) {
                    foreach ($unit['payments']['captures'] ?? [] as $capture) {
                        if (!empty($capture['id'])) {
                            $transactionId = $capture['id'];
                            break 2;
                        }
                    }
                }

                return [
                    'success' => $isCompleted,
                    'data' => [
                        'paypal_order_id' => $data['id'],
                        'status' => $captureStatus,
                        'transaction_id' => $transactionId,
                        'capture_details' => $data,
                    ],
                    'message' => $isCompleted
                        ? 'Payment captured successfully.'
                        : "Payment status: {$captureStatus}",
                ];
            }

            Log::error('PayPal capture order failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => $response->json('message') ?? 'Failed to capture PayPal payment.',
            ];
        } catch (\Exception $e) {
            Log::error('PayPal capture order exception: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while capturing the payment.',
            ];
        }
    }

    /**
     * Get PayPal order details.
     */
    public function getOrder(string $paypalOrderId): array
    {
        $token = $this->getAccessToken();

        if (!$token) {
            return ['success' => false, 'message' => 'Authentication failed.'];
        }

        try {
            $response = Http::withToken($token)
                ->get("{$this->baseUrl}/v2/checkout/orders/{$paypalOrderId}");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to retrieve PayPal order.',
            ];
        } catch (\Exception $e) {
            Log::error('PayPal get order exception: ' . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred.'];
        }
    }
}
