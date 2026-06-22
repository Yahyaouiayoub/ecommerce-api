<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Address;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $client;
    private Order $order;
    private Invoice $invoice;
    private string $adminToken;
    private string $clientToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user
        $this->admin = User::factory()->admin()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@test.com',
        ]);

        // Create client user
        $this->client = User::factory()->create([
            'first_name' => 'Client',
            'last_name' => 'User',
            'email' => 'client@test.com',
        ]);

        // Create API tokens
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;
        $this->clientToken = $this->client->createToken('test')->plainTextToken;

        // Create necessary related data
        $category = Category::create(['name' => 'Test Category', 'slug' => 'test-category']);
        $brand = Brand::create(['name' => 'Test Brand', 'slug' => 'test-brand']);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Test Product',
            'slug' => 'test-product',
            'price' => 100.00,
            'stock' => 10,
            'sku' => 'TST-001',
            'is_active' => true,
            'thumbnail' => '/test.jpg',
        ]);

        ProductImage::create([
            'product_id' => $product->id,
            'image_url' => '/test.jpg',
            'sort_order' => 1,
        ]);

        // Create address
        $address = Address::create([
            'user_id' => $this->client->id,
            'full_name' => 'Client User',
            'email' => 'client@test.com',
            'phone' => '0612345678',
            'address_line1' => '123 Test Street',
            'city' => 'Casablanca',
            'state' => 'Casablanca-Settat',
            'postal_code' => '20000',
            'country' => 'Morocco',
            'is_default' => true,
        ]);

        // Create order
        $this->order = Order::create([
            'user_id' => $this->client->id,
            'order_number' => 'ORD-TEST-' . time(),
            'total_price' => 250.00,
            'status' => 'delivered',
            'payment_method' => 'cod',
            'address_id' => $address->id,
        ]);

        // Create order items
        OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 100.00,
        ]);

        // Create invoice
        $this->invoice = Invoice::create([
            'order_id' => $this->order->id,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'total_amount' => 250.00,
            'paid_amount' => 0,
            'status' => 'unpaid',
            'billing_name' => 'Client User',
            'billing_email' => 'client@test.com',
            'payment_method' => 'cod',
            'issued_at' => now(),
        ]);
    }

    // =========================
    // AUTH HEADER HELPERS
    // =========================

    private function adminHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->adminToken];
    }

    private function clientHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->clientToken];
    }

    // =========================
    // ADMIN: LIST INVOICES
    // =========================

    public function test_admin_can_list_invoices(): void
    {
        $response = $this->getJson('/api/admin/invoices', $this->adminHeaders());

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'invoice_number', 'total_amount', 'paid_amount',
                        'remaining_amount', 'status', 'status_label', 'status_color',
                        'total_formatted', 'paid_formatted', 'remaining_formatted',
                    ],
                ],
                'current_page',
                'last_page',
                'per_page',
                'total',
            ]);
    }

    public function test_admin_can_filter_invoices_by_status(): void
    {
        // Create a paid invoice
        Invoice::create([
            'order_id' => $this->order->id,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'total_amount' => 100.00,
            'paid_amount' => 100.00,
            'status' => 'paid',
            'issued_at' => now(),
        ]);

        $response = $this->getJson('/api/admin/invoices?status=paid', $this->adminHeaders());

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('paid', $response->json('data.0.status'));
    }

    public function test_admin_can_filter_invoices_by_payment_method(): void
    {
        $response = $this->getJson('/api/admin/invoices?payment_method=cod', $this->adminHeaders());

        $response->assertOk();
        foreach ($response->json('data') as $inv) {
            $this->assertEquals('cod', $inv['payment_method']);
        }
    }

    public function test_admin_can_search_invoices(): void
    {
        $response = $this->getJson('/api/admin/invoices?search=' . $this->invoice->invoice_number, $this->adminHeaders());

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    // =========================
    // ADMIN: SHOW INVOICE
    // =========================

    public function test_admin_can_view_invoice_details(): void
    {
        $response = $this->getJson('/api/admin/invoices/' . $this->invoice->id, $this->adminHeaders());

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'invoice_number', 'total_amount', 'paid_amount',
                    'status', 'status_label',
                    'billing_name', 'billing_email',
                    'payment_method',
                    'order' => [
                        'id', 'order_number', 'total_price', 'status',
                        'customer', 'items',
                    ],
                ],
                'meta' => [
                    'subtotal', 'shipping', 'tax', 'total',
                ],
            ]);

        $this->assertEquals($this->invoice->id, $response->json('data.id'));
        $this->assertEquals(200.00, (float) $response->json('meta.subtotal'));
    }

    // =========================
    // ADMIN: CREATE INVOICE
    // =========================

    public function test_admin_can_create_invoice(): void
    {
        $order2 = Order::create([
            'user_id' => $this->client->id,
            'order_number' => 'ORD-TEST-2-' . time(),
            'total_price' => 150.00,
            'status' => 'pending',
            'payment_method' => 'card',
        ]);

        $response = $this->postJson('/api/admin/invoices', [
            'order_id' => $order2->id,
            'total_amount' => 150.00,
            'notes' => 'Test invoice',
        ], $this->adminHeaders());

        $response->assertCreated()
            ->assertJson([
                'message' => 'Invoice created successfully',
            ]);

        $this->assertDatabaseHas('invoices', [
            'order_id' => $order2->id,
            'total_amount' => 150.00,
            'status' => 'unpaid',
        ]);
    }

    public function test_non_admin_cannot_create_invoice(): void
    {
        $response = $this->postJson('/api/admin/invoices', [
            'order_id' => $this->order->id,
            'total_amount' => 100.00,
        ], $this->clientHeaders());

        $response->assertForbidden();
    }

    public function test_cannot_invoice_more_than_order_total(): void
    {
        $response = $this->postJson('/api/admin/invoices', [
            'order_id' => $this->order->id,
            'total_amount' => 99999.00,
        ], $this->adminHeaders());

        $response->assertStatus(422);
    }

    // =========================
    // ADMIN: UPDATE INVOICE
    // =========================

    public function test_admin_can_update_invoice_notes_and_due_date(): void
    {
        $response = $this->putJson('/api/admin/invoices/' . $this->invoice->id, [
            'notes' => 'Updated notes',
            'due_date' => '2026-07-22',
        ], $this->adminHeaders());

        $response->assertOk()
            ->assertJson([
                'message' => 'Invoice updated successfully',
            ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $this->invoice->id,
            'notes' => 'Updated notes',
        ]);
    }

    // =========================
    // INVOICE STATUS TRANSITIONS
    // =========================

    public function test_valid_status_transition_unpaid_to_paid(): void
    {
        $response = $this->putJson('/api/admin/invoices/' . $this->invoice->id . '/status', [
            'status' => 'paid',
        ], $this->adminHeaders());

        $response->assertOk();
        $this->assertEquals('paid', $response->json('data.status'));
    }

    public function test_valid_status_transition_unpaid_to_cancelled(): void
    {
        $response = $this->putJson('/api/admin/invoices/' . $this->invoice->id . '/status', [
            'status' => 'cancelled',
        ], $this->adminHeaders());

        $response->assertOk();
        $this->assertEquals('cancelled', $response->json('data.status'));
    }

    public function test_valid_status_transition_unpaid_to_failed(): void
    {
        $response = $this->putJson('/api/admin/invoices/' . $this->invoice->id . '/status', [
            'status' => 'failed',
        ], $this->adminHeaders());

        $response->assertOk();
        $this->assertEquals('failed', $response->json('data.status'));
    }

    public function test_valid_status_transition_paid_to_refunded(): void
    {
        // First mark as paid
        $this->invoice->update(['status' => 'paid', 'paid_amount' => 250.00]);

        $response = $this->putJson('/api/admin/invoices/' . $this->invoice->id . '/status', [
            'status' => 'refunded',
        ], $this->adminHeaders());

        $response->assertOk();
        $this->assertEquals('refunded', $response->json('data.status'));
    }

    public function test_valid_status_transition_failed_to_unpaid(): void
    {
        // First mark as failed
        $this->invoice->update(['status' => 'failed']);

        $response = $this->putJson('/api/admin/invoices/' . $this->invoice->id . '/status', [
            'status' => 'unpaid',
        ], $this->adminHeaders());

        $response->assertOk();
        $this->assertEquals('unpaid', $response->json('data.status'));
    }

    public function test_valid_status_transition_failed_to_pending(): void
    {
        // First mark as failed
        $this->invoice->update(['status' => 'failed']);

        $response = $this->putJson('/api/admin/invoices/' . $this->invoice->id . '/status', [
            'status' => 'pending',
        ], $this->adminHeaders());

        $response->assertOk();
        $this->assertEquals('pending', $response->json('data.status'));
    }

    public function test_valid_status_transition_pending_to_unpaid(): void
    {
        // First mark as pending
        $this->invoice->update(['status' => 'pending']);

        $response = $this->putJson('/api/admin/invoices/' . $this->invoice->id . '/status', [
            'status' => 'unpaid',
        ], $this->adminHeaders());

        $response->assertOk();
        $this->assertEquals('unpaid', $response->json('data.status'));
    }

    public function test_valid_status_transition_partially_paid_to_paid(): void
    {
        $this->invoice->update(['status' => 'partially_paid', 'paid_amount' => 100.00]);

        $response = $this->putJson('/api/admin/invoices/' . $this->invoice->id . '/status', [
            'status' => 'paid',
        ], $this->adminHeaders());

        $response->assertOk();
        $this->assertEquals('paid', $response->json('data.status'));
    }

    public function test_invalid_status_transition_unpaid_to_refunded(): void
    {
        // Can't go from unpaid to refunded directly
        $response = $this->putJson('/api/admin/invoices/' . $this->invoice->id . '/status', [
            'status' => 'refunded',
        ], $this->adminHeaders());

        $response->assertStatus(422);
    }

    public function test_invalid_status_transition_cancelled_to_paid(): void
    {
        // Terminal state - can't change from cancelled
        $this->invoice->update(['status' => 'cancelled']);

        $response = $this->putJson('/api/admin/invoices/' . $this->invoice->id . '/status', [
            'status' => 'paid',
        ], $this->adminHeaders());

        $response->assertStatus(422);
    }

    public function test_invalid_status_transition_refunded_to_unpaid(): void
    {
        // Terminal state - can't change from refunded
        $this->invoice->update(['status' => 'refunded']);

        $response = $this->putJson('/api/admin/invoices/' . $this->invoice->id . '/status', [
            'status' => 'unpaid',
        ], $this->adminHeaders());

        $response->assertStatus(422);
    }

    public function test_non_admin_cannot_update_status(): void
    {
        $response = $this->putJson('/api/admin/invoices/' . $this->invoice->id . '/status', [
            'status' => 'paid',
        ], $this->clientHeaders());

        $response->assertForbidden();
    }

    // =========================
    // ADMIN: DELETE INVOICE
    // =========================

    public function test_admin_can_delete_unpaid_invoice(): void
    {
        $response = $this->deleteJson('/api/admin/invoices/' . $this->invoice->id, [], $this->adminHeaders());

        $response->assertOk();
        $this->assertDatabaseMissing('invoices', ['id' => $this->invoice->id]);
    }

    public function test_cannot_delete_paid_invoice(): void
    {
        $this->invoice->update(['paid_amount' => 250.00, 'status' => 'paid']);

        $response = $this->deleteJson('/api/admin/invoices/' . $this->invoice->id, [], $this->adminHeaders());

        $response->assertStatus(422);
        $this->assertDatabaseHas('invoices', ['id' => $this->invoice->id]);
    }

    // =========================
    // INVOICE STATISTICS
    // =========================

    public function test_invoice_statistics(): void
    {
        // Create invoices in various states
        Invoice::create([
            'order_id' => $this->order->id,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'total_amount' => 100.00,
            'paid_amount' => 100.00,
            'status' => 'paid',
            'paid_at' => now(),
            'issued_at' => now(),
        ]);

        Invoice::create([
            'order_id' => $this->order->id,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'total_amount' => 200.00,
            'paid_amount' => 0,
            'status' => 'pending',
            'issued_at' => now(),
        ]);

        Invoice::create([
            'order_id' => $this->order->id,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'total_amount' => 150.00,
            'paid_amount' => 150.00,
            'status' => 'paid',
            'paid_at' => now(),
            'issued_at' => now(),
        ]);

        $response = $this->getJson('/api/admin/invoices/stats', $this->adminHeaders());

        $response->assertOk()
            ->assertJsonStructure([
                'total_invoices',
                'paid_invoices',
                'pending_invoices',
                'refunded_invoices',
                'failed_invoices',
                'cancelled_invoices',
                'total_revenue',
                'total_pending_amount',
            ]);

        $this->assertEquals(4, $response->json('total_invoices')); // 1 from setup + 3 new
        $this->assertEquals(2, $response->json('paid_invoices'));
        $this->assertEquals(250.00, (float) $response->json('total_revenue')); // 100 + 150
    }

    // =========================
    // REGISTER PAYMENT
    // =========================

    public function test_admin_can_register_payment(): void
    {
        $response = $this->postJson('/api/admin/invoices/' . $this->invoice->id . '/pay', [
            'amount' => 250.00,
            'payment_type' => 'full',
        ], $this->adminHeaders());

        $response->assertOk()
            ->assertJson([
                'message' => 'Payment registered successfully',
            ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $this->invoice->id,
            'paid_amount' => 250.00,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $this->invoice->id,
            'amount' => 250.00,
        ]);
    }

    public function test_cannot_pay_already_paid_invoice(): void
    {
        $this->invoice->update(['paid_amount' => 250.00, 'status' => 'paid']);

        $response = $this->postJson('/api/admin/invoices/' . $this->invoice->id . '/pay', [
            'amount' => 100.00,
            'payment_type' => 'full',
        ], $this->adminHeaders());

        $response->assertStatus(422);
    }

    public function test_cannot_pay_more_than_remaining(): void
    {
        $response = $this->postJson('/api/admin/invoices/' . $this->invoice->id . '/pay', [
            'amount' => 99999.00,
            'payment_type' => 'full',
        ], $this->adminHeaders());

        $response->assertStatus(422);
    }

    // =========================
    // CLIENT: LIST INVOICES
    // =========================

    public function test_client_can_list_own_invoices(): void
    {
        $response = $this->getJson('/api/invoices', $this->clientHeaders());

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'invoice_number', 'total_amount', 'status'],
                ],
            ]);
    }

    public function test_client_can_view_own_invoice(): void
    {
        $response = $this->getJson('/api/invoices/' . $this->invoice->id, $this->clientHeaders());

        $response->assertOk();
        $this->assertEquals($this->invoice->id, $response->json('data.id'));
    }

    public function test_client_cannot_view_others_invoice(): void
    {
        // Create another client
        $otherClient = User::factory()->create();
        $otherToken = $otherClient->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/invoices/' . $this->invoice->id, [
            'Authorization' => 'Bearer ' . $otherToken,
        ]);

        $response->assertForbidden();
    }

    // =========================
    // PDF GENERATION
    // =========================

    public function test_admin_can_preview_invoice_pdf(): void
    {
        $response = $this->getJson('/api/admin/invoices/' . $this->invoice->id . '/pdf', [
            'Authorization' => 'Bearer ' . $this->adminToken,
        ]);

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type') ?? '');
    }

    public function test_admin_can_download_invoice_pdf(): void
    {
        $response = $this->getJson('/api/admin/invoices/' . $this->invoice->id . '/download', [
            'Authorization' => 'Bearer ' . $this->adminToken,
        ]);

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type') ?? '');
    }

    public function test_pdf_auth_with_token_query_param(): void
    {
        $response = $this->get('/api/admin/invoices/' . $this->invoice->id . '/pdf?token=' . $this->adminToken);

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type') ?? '');
    }

    public function test_pdf_returns_401_without_auth(): void
    {
        $response = $this->getJson('/api/admin/invoices/' . $this->invoice->id . '/pdf');

        $response->assertUnauthorized();
    }

    public function test_pdf_returns_401_with_invalid_token(): void
    {
        $response = $this->get('/api/admin/invoices/' . $this->invoice->id . '/pdf?token=invalid-token');

        $response->assertUnauthorized();
    }

    public function test_client_can_download_own_invoice_pdf(): void
    {
        $response = $this->get('/api/invoices/' . $this->invoice->id . '/download?token=' . $this->clientToken);

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type') ?? '');
    }

    public function test_client_cannot_download_others_invoice_pdf(): void
    {
        $otherClient = User::factory()->create();
        $otherToken = $otherClient->createToken('test')->plainTextToken;

        $response = $this->get('/api/invoices/' . $this->invoice->id . '/download?token=' . $otherToken);

        $response->assertForbidden();
    }

    // =========================
    // ORDER INVOICE SUMMARY
    // =========================

    public function test_admin_can_get_order_invoice_summary(): void
    {
        $response = $this->getJson('/api/admin/orders/' . $this->order->id . '/invoice-summary', $this->adminHeaders());

        $response->assertOk()
            ->assertJsonStructure([
                'order_id', 'order_number', 'order_total',
                'total_invoiced', 'total_paid', 'remaining_to_pay',
                'invoice_count', 'invoices',
            ]);

        $this->assertEquals($this->order->id, $response->json('order_id'));
        $this->assertEquals(250.00, (float) $response->json('order_total'));
        $this->assertEquals(1, $response->json('invoice_count'));
    }

    // =========================
    // INVOICE MODEL TESTS
    // =========================

    public function test_invoice_generate_number_creates_unique_numbers(): void
    {
        $num1 = Invoice::generateInvoiceNumber();

        // Save an invoice with the first number so the next generated number is different
        Invoice::create([
            'order_id' => $this->order->id,
            'invoice_number' => $num1,
            'total_amount' => 50.00,
            'paid_amount' => 0,
            'status' => 'unpaid',
            'issued_at' => now(),
        ]);

        $num2 = Invoice::generateInvoiceNumber();

        $this->assertNotEquals($num1, $num2);
        $this->assertStringContainsString('INV-', $num1);
    }

    public function test_invoice_remaining_amount_calculation(): void
    {
        $this->invoice->update(['total_amount' => 1000.00, 'paid_amount' => 300.00]);

        $this->assertEquals(700.00, $this->invoice->fresh()->remaining_amount);
    }

    public function test_invoice_recalculate_status(): void
    {
        $inv = $this->invoice->fresh();

        // Full payment -> paid
        $inv->paid_amount = 250.00;
        $inv->recalculateStatus()->save();
        $this->assertEquals('paid', $inv->fresh()->status);

        // No payment -> unpaid
        $inv->paid_amount = 0;
        $inv->recalculateStatus()->save();
        $this->assertEquals('unpaid', $inv->fresh()->status);

        // Partial payment -> partially_paid
        $inv->paid_amount = 125.00;
        $inv->recalculateStatus()->save();
        $this->assertEquals('partially_paid', $inv->fresh()->status);

        // Manual status should not be overridden
        $inv->status = 'refunded';
        $inv->paid_amount = 0;
        $inv->recalculateStatus()->save();
        $this->assertEquals('refunded', $inv->fresh()->status);
    }

    public function test_invoice_status_labels(): void
    {
        $statuses = [
            'unpaid' => 'Unpaid',
            'partially_paid' => 'Partially Paid',
            'paid' => 'Paid',
            'pending' => 'Pending',
            'failed' => 'Failed',
            'refunded' => 'Refunded',
            'cancelled' => 'Cancelled',
        ];

        foreach ($statuses as $status => $label) {
            $this->invoice->status = $status;
            $this->assertEquals($label, $this->invoice->status_label);
        }
    }

    public function test_invoice_status_colors(): void
    {
        $this->invoice->status = 'paid';
        $this->assertEquals('emerald', $this->invoice->status_color);

        $this->invoice->status = 'failed';
        $this->assertEquals('red', $this->invoice->status_color);

        $this->invoice->status = 'refunded';
        $this->assertEquals('purple', $this->invoice->status_color);
    }

    public function test_invoice_helper_methods(): void
    {
        $this->invoice->status = 'paid';
        $this->assertTrue($this->invoice->isPaid());
        $this->assertFalse($this->invoice->isUnpaid());

        $this->invoice->status = 'unpaid';
        $this->assertTrue($this->invoice->isUnpaid());
        $this->assertFalse($this->invoice->isPaid());

        $this->invoice->status = 'pending';
        $this->assertTrue($this->invoice->isPending());

        $this->invoice->status = 'failed';
        $this->assertTrue($this->invoice->isFailed());

        $this->invoice->status = 'refunded';
        $this->assertTrue($this->invoice->isRefunded());

        $this->invoice->status = 'cancelled';
        $this->assertTrue($this->invoice->isCancelled());

        $this->invoice->status = 'partially_paid';
        $this->assertTrue($this->invoice->isPartiallyPaid());
    }

    public function test_mark_as_refunded_only_works_on_paid_or_partial(): void
    {
        // Unpaid invoice -> should not mark as refunded
        $this->invoice->markAsRefunded();
        $this->assertEquals('unpaid', $this->invoice->fresh()->status);

        // Paid invoice -> should mark as refunded
        $this->invoice->update(['status' => 'paid', 'paid_amount' => 250.00]);
        $this->invoice->fresh()->markAsRefunded();
        $this->assertEquals('refunded', $this->invoice->fresh()->status);
    }

    public function test_mark_as_cancelled_only_works_on_zero_paid(): void
    {
        // Invoice with paid_amount > 0 should NOT be cancellable
        $invoiceWithPayment = Invoice::create([
            'order_id' => $this->order->id,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'total_amount' => 200.00,
            'paid_amount' => 100.00,
            'status' => 'unpaid',
            'issued_at' => now(),
        ]);

        $invoiceWithPayment->markAsCancelled();
        $this->assertNotEquals('cancelled', $invoiceWithPayment->fresh()->status);

        // Invoice with paid_amount = 0 should be cancellable
        $invoiceZeroPaid = Invoice::create([
            'order_id' => $this->order->id,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'total_amount' => 200.00,
            'paid_amount' => 0,
            'status' => 'unpaid',
            'issued_at' => now(),
        ]);

        $invoiceZeroPaid->markAsCancelled();
        $this->assertEquals('cancelled', $invoiceZeroPaid->fresh()->status);
    }

    // =========================
    // REGISTER PAYMENT ON INVOICE MODEL
    // =========================

    public function test_register_payment_creates_payment_and_updates_invoice(): void
    {
        $payment = $this->invoice->registerPayment(100.00, 'cod', 'partial_50');

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'invoice_id' => $this->invoice->id,
            'amount' => 100.00,
            'payment_type' => 'partial_50',
        ]);

        $this->assertEquals(100.00, $this->invoice->fresh()->paid_amount);
    }
}
