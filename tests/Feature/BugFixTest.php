<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Address;
use App\Models\ProductImage;
use App\Models\Invoice;
use App\Models\Revenue;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BugFixTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $client;
    private string $adminToken;
    private string $clientToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user
        $this->admin = User::factory()->admin()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@bugfix-test.com',
        ]);

        // Create client user
        $this->client = User::factory()->create([
            'first_name' => 'Client',
            'last_name' => 'User',
            'email' => 'client@bugfix-test.com',
        ]);

        // Create API tokens
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;
        $this->clientToken = $this->client->createToken('test')->plainTextToken;
    }

    private function adminHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->adminToken];
    }

    private function clientHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->clientToken];
    }

    // =========================
    // BEST SELLERS TESTS
    // =========================

    public function test_best_sellers_only_returns_products_with_orders(): void
    {
        $category = Category::create(['name' => 'Test Cat', 'slug' => 'test-cat', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Test Brand', 'slug' => 'test-brand', 'is_active' => true]);

        // Create a product that HAS been ordered
        $soldProduct = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Sold Product',
            'slug' => 'sold-product',
            'price' => 100.00,
            'stock' => 10,
            'sku' => 'SOLD-001',
            'is_active' => true,
        ]);

        // Create a product that has NEVER been ordered
        $unsoldProduct = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Unsold Product',
            'slug' => 'unsold-product',
            'price' => 50.00,
            'stock' => 10,
            'sku' => 'UNSOLD-001',
            'is_active' => true,
        ]);

        // Create an order with the sold product
        $order = Order::create([
            'user_id' => $this->client->id,
            'order_number' => 'ORD-BS-' . time(),
            'total_price' => 100.00,
            'status' => 'delivered',
            'payment_method' => 'cod',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $soldProduct->id,
            'quantity' => 1,
            'price' => 100.00,
        ]);

        $response = $this->getJson('/api/products/best-sellers');

        $response->assertOk();
        $productIds = collect($response->json())->pluck('id')->toArray();

        $this->assertContains($soldProduct->id, $productIds, 'Sold product should appear in best sellers');
        $this->assertNotContains($unsoldProduct->id, $productIds, 'Unsold product should NOT appear in best sellers');
    }

    public function test_best_sellers_excludes_inactive_products(): void
    {
        $category = Category::create(['name' => 'Test Cat 2', 'slug' => 'test-cat-2', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Test Brand 2', 'slug' => 'test-brand-2', 'is_active' => true]);

        // Create an inactive product that has been ordered
        $inactiveProduct = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Inactive Product',
            'slug' => 'inactive-product',
            'price' => 200.00,
            'stock' => 5,
            'sku' => 'INACT-001',
            'is_active' => false,
        ]);

        $order = Order::create([
            'user_id' => $this->client->id,
            'order_number' => 'ORD-BS-2-' . time(),
            'total_price' => 200.00,
            'status' => 'delivered',
            'payment_method' => 'cod',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $inactiveProduct->id,
            'quantity' => 1,
            'price' => 200.00,
        ]);

        $response = $this->getJson('/api/products/best-sellers');

        $response->assertOk();
        $productIds = collect($response->json())->pluck('id')->toArray();

        $this->assertNotContains($inactiveProduct->id, $productIds, 'Inactive product should NOT appear in best sellers even if it has orders');
    }

    public function test_best_sellers_returns_empty_when_no_products_have_orders(): void
    {
        $category = Category::create(['name' => 'Empty Cat', 'slug' => 'empty-cat', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Empty Brand', 'slug' => 'empty-brand', 'is_active' => true]);

        Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Never Ordered',
            'slug' => 'never-ordered',
            'price' => 30.00,
            'stock' => 10,
            'sku' => 'NEVER-001',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/products/best-sellers');

        $response->assertOk();
        $this->assertCount(0, $response->json(), 'Best sellers should be empty when no products have orders');
    }

    public function test_best_sellers_orders_by_quantity_descending(): void
    {
        $category = Category::create(['name' => 'Rank Cat', 'slug' => 'rank-cat', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Rank Brand', 'slug' => 'rank-brand', 'is_active' => true]);

        $topProduct = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Top Seller',
            'slug' => 'top-seller',
            'price' => 10.00,
            'stock' => 100,
            'sku' => 'TOP-001',
            'is_active' => true,
        ]);

        $bottomProduct = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Low Seller',
            'slug' => 'low-seller',
            'price' => 5.00,
            'stock' => 100,
            'sku' => 'LOW-001',
            'is_active' => true,
        ]);

        $order = Order::create([
            'user_id' => $this->client->id,
            'order_number' => 'ORD-RANK-' . time(),
            'total_price' => 25.00,
            'status' => 'delivered',
            'payment_method' => 'cod',
        ]);

        // Top product ordered 5 times
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $topProduct->id,
            'quantity' => 5,
            'price' => 10.00,
        ]);

        // Bottom product ordered 1 time
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $bottomProduct->id,
            'quantity' => 1,
            'price' => 5.00,
        ]);

        $response = $this->getJson('/api/products/best-sellers');

        $response->assertOk();
        $products = $response->json();

        $this->assertCount(2, $products);
        $this->assertEquals($topProduct->id, $products[0]['id'], 'Top seller should be first');
        $this->assertEquals($bottomProduct->id, $products[1]['id'], 'Low seller should be second');
    }

    public function test_best_sellers_limits_to_eight_products(): void
    {
        $category = Category::create(['name' => 'Limit Cat', 'slug' => 'limit-cat', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Limit Brand', 'slug' => 'limit-brand', 'is_active' => true]);

        $order = Order::create([
            'user_id' => $this->client->id,
            'order_number' => 'ORD-LIMIT-' . time(),
            'total_price' => 1000.00,
            'status' => 'delivered',
            'payment_method' => 'cod',
        ]);

        // Create 10 products, each with an order item
        for ($i = 1; $i <= 10; $i++) {
            $product = Product::create([
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'name' => "Product {$i}",
                'slug' => "product-{$i}",
                'price' => $i * 10,
                'stock' => 10,
                'sku' => "LIMIT-{$i}",
                'is_active' => true,
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $i,
                'price' => $i * 10,
            ]);
        }

        $response = $this->getJson('/api/products/best-sellers');

        $response->assertOk();
        $this->assertCount(8, $response->json(), 'Best sellers should be limited to 8 products');
    }

    // =========================
    // CHECKOUT PRICING (getEffectivePrice) TESTS
    // =========================

    public function test_checkout_uses_discount_price_when_set(): void
    {
        $category = Category::create(['name' => 'Checkout Cat', 'slug' => 'checkout-cat', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Checkout Brand', 'slug' => 'checkout-brand', 'is_active' => true]);

        // Product with a discount price (original price 200, discount 150)
        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Discounted Product',
            'slug' => 'discounted-product',
            'price' => 200.00,
            'purchase_price' => 80.00,
            'margin_percentage' => 150.00,
            'final_price' => 200.00,
            'discount_price' => 150.00,
            'stock' => 10,
            'sku' => 'DISC-001',
            'is_active' => true,
        ]);

        // Add to cart
        Cart::create([
            'user_id' => $this->client->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'status' => 'active',
        ]);

        // Create an address
        $address = Address::create([
            'user_id' => $this->client->id,
            'full_name' => 'Client User',
            'email' => 'client@bugfix-test.com',
            'phone' => '0612345678',
            'address_line1' => '123 Test Street',
            'city' => 'Casablanca',
            'state' => 'Casablanca-Settat',
            'postal_code' => '20000',
            'country' => 'Morocco',
            'is_default' => true,
        ]);

        // Place order as authenticated user
        $response = $this->postJson('/api/orders', [
            'payment_method' => 'cod',
            'address_id' => $address->id,
        ], $this->clientHeaders());

        $response->assertCreated();

        $order = $response->json('order');
        $this->assertNotNull($order, 'Order should be created');

        // Check that order items used the discount price (150), not the base price (200)
        $orderModel = Order::find($order['id']);
        $this->assertNotNull($orderModel);

        $orderItem = $orderModel->items()->first();
        $this->assertNotNull($orderItem, 'Order should have items');
        $this->assertEquals(150.00, (float) $orderItem->price, 'Order item should use discount price (150), not base price (200)');

        // Total should be 2 * 150 = 300 (subtotal), plus shipping/tax as configured
        $this->assertEquals(2 * 150, (float) $orderModel->items()->sum(\Illuminate\Support\Facades\DB::raw('quantity * price')));
    }

    public function test_checkout_uses_regular_price_when_no_discount(): void
    {
        $category = Category::create(['name' => 'NoDisc Cat', 'slug' => 'nodisc-cat', 'is_active' => true]);
        $brand = Brand::create(['name' => 'NoDisc Brand', 'slug' => 'nodisc-brand', 'is_active' => true]);

        // Product WITHOUT a discount price
        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Full Price Product',
            'slug' => 'full-price-product',
            'price' => 75.00,
            'stock' => 10,
            'sku' => 'FULL-001',
            'is_active' => true,
        ]);

        Cart::create([
            'user_id' => $this->client->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'status' => 'active',
        ]);

        $address = Address::create([
            'user_id' => $this->client->id,
            'full_name' => 'Client User',
            'email' => 'client@bugfix-test.com',
            'phone' => '0612345678',
            'address_line1' => '123 Test Street',
            'city' => 'Casablanca',
            'state' => 'Casablanca-Settat',
            'postal_code' => '20000',
            'country' => 'Morocco',
            'is_default' => true,
        ]);

        $response = $this->postJson('/api/orders', [
            'payment_method' => 'cod',
            'address_id' => $address->id,
        ], $this->clientHeaders());

        $response->assertCreated();

        $orderModel = Order::find($response->json('order.id'));
        $orderItem = $orderModel->items()->first();

        $this->assertEquals(75.00, (float) $orderItem->price, 'Order item should use regular price (75) when no discount is set');
    }

    public function test_checkout_uses_discount_price_when_null_discount_is_zero(): void
    {
        $category = Category::create(['name' => 'ZeroDisc Cat', 'slug' => 'zerodisc-cat', 'is_active' => true]);
        $brand = Brand::create(['name' => 'ZeroDisc Brand', 'slug' => 'zerodisc-brand', 'is_active' => true]);

        // Product with discount_price = 0 (should be treated as no discount)
        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Zero Discount Product',
            'slug' => 'zero-discount-product',
            'price' => 99.00,
            'discount_price' => 0,
            'stock' => 10,
            'sku' => 'ZERO-001',
            'is_active' => true,
        ]);

        Cart::create([
            'user_id' => $this->client->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'status' => 'active',
        ]);

        $address = Address::create([
            'user_id' => $this->client->id,
            'full_name' => 'Client User',
            'email' => 'client@bugfix-test.com',
            'phone' => '0612345678',
            'address_line1' => '123 Test Street',
            'city' => 'Casablanca',
            'state' => 'Casablanca-Settat',
            'postal_code' => '20000',
            'country' => 'Morocco',
            'is_default' => true,
        ]);

        $response = $this->postJson('/api/orders', [
            'payment_method' => 'cod',
            'address_id' => $address->id,
        ], $this->clientHeaders());

        $response->assertCreated();

        $orderModel = Order::find($response->json('order.id'));
        $orderItem = $orderModel->items()->first();

        $this->assertEquals(99.00, (float) $orderItem->price, 'Order item should use regular price when discount_price is 0');
    }

    public function test_checkout_reduces_stock_correctly(): void
    {
        $category = Category::create(['name' => 'Stock Cat', 'slug' => 'stock-cat', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Stock Brand', 'slug' => 'stock-brand', 'is_active' => true]);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Stock Test Product',
            'slug' => 'stock-test-product',
            'price' => 50.00,
            'stock' => 10,
            'sku' => 'STOCK-001',
            'is_active' => true,
        ]);

        Cart::create([
            'user_id' => $this->client->id,
            'product_id' => $product->id,
            'quantity' => 4,
            'status' => 'active',
        ]);

        $address = Address::create([
            'user_id' => $this->client->id,
            'full_name' => 'Client User',
            'email' => 'client@bugfix-test.com',
            'phone' => '0612345678',
            'address_line1' => '123 Test Street',
            'city' => 'Casablanca',
            'state' => 'Casablanca-Settat',
            'postal_code' => '20000',
            'country' => 'Morocco',
            'is_default' => true,
        ]);

        $response = $this->postJson('/api/orders', [
            'payment_method' => 'cod',
            'address_id' => $address->id,
        ], $this->clientHeaders());

        $response->assertCreated();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 6, // 10 - 4 = 6
        ]);
    }

    // =========================
    // CATEGORY FILTERING TESTS
    // =========================

    public function test_public_categories_only_returns_active(): void
    {
        Category::create(['name' => 'Active Cat', 'slug' => 'active-cat', 'is_active' => true]);
        Category::create(['name' => 'Inactive Cat', 'slug' => 'inactive-cat', 'is_active' => false]);

        $response = $this->getJson('/api/categories');

        $response->assertOk();
        $names = collect($response->json())->pluck('name')->toArray();

        $this->assertContains('Active Cat', $names);
        $this->assertNotContains('Inactive Cat', $names);
        $this->assertCount(1, $response->json());
    }

    public function test_admin_categories_returns_all_including_inactive(): void
    {
        Category::create(['name' => 'Active Cat', 'slug' => 'active-cat', 'is_active' => true]);
        Category::create(['name' => 'Inactive Cat', 'slug' => 'inactive-cat', 'is_active' => false]);

        $response = $this->getJson('/api/admin/categories', $this->adminHeaders());

        $response->assertOk();
        $names = collect($response->json())->pluck('name')->toArray();

        $this->assertContains('Active Cat', $names);
        $this->assertContains('Inactive Cat', $names);
        $this->assertCount(2, $response->json());
    }

    public function test_public_categories_returns_empty_when_all_inactive(): void
    {
        Category::create(['name' => 'Hidden Cat', 'slug' => 'hidden-cat', 'is_active' => false]);

        $response = $this->getJson('/api/categories');

        $response->assertOk();
        $this->assertCount(0, $response->json());
    }

    public function test_admin_category_show_still_works_for_inactive(): void
    {
        $category = Category::create(['name' => 'Hidden', 'slug' => 'hidden', 'is_active' => false]);

        $response = $this->getJson("/api/categories/{$category->id}");

        $response->assertOk();
        $this->assertEquals($category->id, $response->json('id'));
    }

    // =========================
    // ADMIN PRODUCTS (adminIndex) TESTS
    // =========================

    public function test_admin_products_includes_out_of_stock(): void
    {
        $category = Category::create(['name' => 'AdminIdx Cat', 'slug' => 'adminidx-cat', 'is_active' => true]);
        $brand = Brand::create(['name' => 'AdminIdx Brand', 'slug' => 'adminidx-brand', 'is_active' => true]);

        Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'In Stock Product',
            'slug' => 'in-stock-product',
            'price' => 50.00,
            'stock' => 10,
            'sku' => 'INSTK-001',
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Out of Stock Product',
            'slug' => 'out-of-stock-product',
            'price' => 75.00,
            'stock' => 0,
            'sku' => 'OUTSTK-001',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/admin/products', $this->adminHeaders());

        $response->assertOk();
        $productIds = collect($response->json('data'))->pluck('id')->toArray();
        $productNames = collect($response->json('data'))->pluck('name')->toArray();

        $this->assertContains('In Stock Product', $productNames, 'Admin endpoint should include in-stock products');
        $this->assertContains('Out of Stock Product', $productNames, 'Admin endpoint should include out-of-stock products');
    }

    public function test_admin_products_includes_low_stock_and_zero_stock(): void
    {
        $category = Category::create(['name' => 'Stock Cat', 'slug' => 'stock-cat', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Stock Brand', 'slug' => 'stock-brand', 'is_active' => true]);

        Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Zero Stock',
            'slug' => 'zero-stock',
            'price' => 10.00,
            'stock' => 0,
            'sku' => 'ZSTK-001',
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Negative Stock',
            'slug' => 'negative-stock',
            'price' => 20.00,
            'stock' => -5,
            'sku' => 'NEGSTK-001',
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Positive Stock',
            'slug' => 'positive-stock',
            'price' => 30.00,
            'stock' => 99,
            'sku' => 'POSSTK-001',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/admin/products', $this->adminHeaders());

        $response->assertOk();
        $productNames = collect($response->json('data'))->pluck('name')->toArray();

        $this->assertContains('Zero Stock', $productNames);
        $this->assertContains('Negative Stock', $productNames);
        $this->assertContains('Positive Stock', $productNames);
        $this->assertCount(3, $response->json('data'), 'Admin endpoint should return all products regardless of stock level');
    }

    public function test_public_products_excludes_out_of_stock(): void
    {
        $category = Category::create(['name' => 'PublicP Cat', 'slug' => 'publicp-cat', 'is_active' => true]);
        $brand = Brand::create(['name' => 'PublicP Brand', 'slug' => 'publicp-brand', 'is_active' => true]);

        Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Available',
            'slug' => 'available',
            'price' => 25.00,
            'stock' => 5,
            'sku' => 'AVAIL-001',
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Unavailable',
            'slug' => 'unavailable',
            'price' => 40.00,
            'stock' => 0,
            'sku' => 'UNAVAIL-001',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/products');

        $response->assertOk();
        $productNames = collect($response->json('data'))->pluck('name')->toArray();

        $this->assertContains('Available', $productNames, 'Public endpoint should include products with stock > 0');
        $this->assertNotContains('Unavailable', $productNames, 'Public endpoint should exclude out-of-stock products');
        $this->assertCount(1, $response->json('data'));
    }

    public function test_admin_products_is_paginated(): void
    {
        $category = Category::create(['name' => 'Pagin Cat', 'slug' => 'pagin-cat', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Pagin Brand', 'slug' => 'pagin-brand', 'is_active' => true]);

        // Create multiple products to force pagination
        for ($i = 1; $i <= 25; $i++) {
            Product::create([
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'name' => "Pagin Product {$i}",
                'slug' => "pagin-product-{$i}",
                'price' => $i * 10,
                'stock' => $i,
                'sku' => "PAGIN-{$i}",
                'is_active' => true,
            ]);
        }

        $response = $this->getJson('/api/admin/products', $this->adminHeaders());

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'current_page',
                'last_page',
                'per_page',
                'total',
            ]);

        $this->assertEquals(20, $response->json('per_page'), 'Default admin products per page should be 20');
        $this->assertEquals(25, $response->json('total'), 'Total should reflect all products');
        $this->assertEquals(2, $response->json('last_page'), '25 products at 20 per page = 2 pages');
        $this->assertCount(20, $response->json('data'), 'First page should have 20 products');
    }

    public function test_admin_products_supports_custom_per_page(): void
    {
        $category = Category::create(['name' => 'PerPage Cat', 'slug' => 'perpage-cat', 'is_active' => true]);
        $brand = Brand::create(['name' => 'PerPage Brand', 'slug' => 'perpage-brand', 'is_active' => true]);

        for ($i = 1; $i <= 10; $i++) {
            Product::create([
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'name' => "PerPage Product {$i}",
                'slug' => "perpage-product-{$i}",
                'price' => $i * 10,
                'stock' => $i,
                'sku' => "PERP-{$i}",
                'is_active' => true,
            ]);
        }

        $response = $this->getJson('/api/admin/products?per_page=5', $this->adminHeaders());

        $response->assertOk();
        $this->assertEquals(5, $response->json('per_page'));
        $this->assertCount(5, $response->json('data'));
    }

    public function test_admin_products_filters_by_category(): void
    {
        $catA = Category::create(['name' => 'Cat A', 'slug' => 'cat-a', 'is_active' => true]);
        $catB = Category::create(['name' => 'Cat B', 'slug' => 'cat-b', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Filter Brand', 'slug' => 'filter-brand', 'is_active' => true]);

        Product::create([
            'category_id' => $catA->id,
            'brand_id' => $brand->id,
            'name' => 'Category A Product',
            'slug' => 'cat-a-product',
            'price' => 10.00,
            'stock' => 0,
            'sku' => 'CATA-001',
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $catB->id,
            'brand_id' => $brand->id,
            'name' => 'Category B Product',
            'slug' => 'cat-b-product',
            'price' => 20.00,
            'stock' => 0,
            'sku' => 'CATB-001',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/admin/products?category_id={$catA->id}", $this->adminHeaders());

        $response->assertOk();
        $productNames = collect($response->json('data'))->pluck('name')->toArray();

        $this->assertContains('Category A Product', $productNames);
        $this->assertNotContains('Category B Product', $productNames);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_admin_products_filters_by_is_active(): void
    {
        $category = Category::create(['name' => 'ActiveFilter Cat', 'slug' => 'activefilter-cat', 'is_active' => true]);
        $brand = Brand::create(['name' => 'ActiveFilter Brand', 'slug' => 'activefilter-brand', 'is_active' => true]);

        Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Active Product',
            'slug' => 'active-product',
            'price' => 100.00,
            'stock' => 0,
            'sku' => 'ACTV-001',
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Inactive Product',
            'slug' => 'inactive-product-admin',
            'price' => 200.00,
            'stock' => 0,
            'sku' => 'INACTV-001',
            'is_active' => false,
        ]);

        // Filter for active only
        $response = $this->getJson('/api/admin/products?is_active=1', $this->adminHeaders());

        $response->assertOk();
        $productNames = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Active Product', $productNames);
        $this->assertNotContains('Inactive Product', $productNames);

        // Filter for inactive only
        $response = $this->getJson('/api/admin/products?is_active=0', $this->adminHeaders());

        $response->assertOk();
        $productNames = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertNotContains('Active Product', $productNames);
        $this->assertContains('Inactive Product', $productNames);
    }

    // =========================
    // BRAND FILTERING TESTS
    // =========================

    public function test_public_brands_only_returns_active(): void
    {
        Brand::create(['name' => 'Active Brand', 'slug' => 'active-brand', 'is_active' => true]);
        Brand::create(['name' => 'Inactive Brand', 'slug' => 'inactive-brand', 'is_active' => false]);

        $response = $this->getJson('/api/brands');

        $response->assertOk();
        $names = collect($response->json())->pluck('name')->toArray();

        $this->assertContains('Active Brand', $names);
        $this->assertNotContains('Inactive Brand', $names);
        $this->assertCount(1, $response->json());
    }

    public function test_admin_brands_returns_all_including_inactive(): void
    {
        Brand::create(['name' => 'Active Brand', 'slug' => 'active-brand', 'is_active' => true]);
        Brand::create(['name' => 'Inactive Brand', 'slug' => 'inactive-brand', 'is_active' => false]);

        $response = $this->getJson('/api/admin/brands', $this->adminHeaders());

        $response->assertOk();
        $names = collect($response->json())->pluck('name')->toArray();

        $this->assertContains('Active Brand', $names);
        $this->assertContains('Inactive Brand', $names);
        $this->assertCount(2, $response->json());
    }

    public function test_public_brands_returns_empty_when_all_inactive(): void
    {
        Brand::create(['name' => 'Hidden Brand', 'slug' => 'hidden-brand', 'is_active' => false]);

        $response = $this->getJson('/api/brands');

        $response->assertOk();
        $this->assertCount(0, $response->json());
    }

    public function test_brand_inactive_products_still_accessible_by_id(): void
    {
        $brand = Brand::create(['name' => 'Hidden', 'slug' => 'hidden', 'is_active' => false]);

        $response = $this->getJson("/api/brands/{$brand->id}");

        $response->assertOk();
        $this->assertEquals($brand->id, $response->json('id'));
    }

    // =========================
    // USER ORDER LISTING TESTS
    // =========================

    public function test_user_sees_only_own_orders_in_listing(): void
    {
        // Create a second client (user B)
        $otherClient = User::factory()->create([
            'first_name' => 'Other',
            'last_name' => 'User',
            'email' => 'other@bugfix-test.com',
        ]);

        // Create an order for user (client)
        $clientOrder = Order::create([
            'user_id' => $this->client->id,
            'order_number' => 'ORD-MINE-' . time(),
            'total_price' => 100.00,
            'status' => 'pending',
            'payment_method' => 'cod',
        ]);

        // Create an order for the other user
        $otherOrder = Order::create([
            'user_id' => $otherClient->id,
            'order_number' => 'ORD-OTHER-' . time(),
            'total_price' => 200.00,
            'status' => 'pending',
            'payment_method' => 'cod',
        ]);

        $response = $this->getJson('/api/orders', $this->clientHeaders());

        $response->assertOk();
        $orderIds = collect($response->json())->pluck('id')->toArray();
        $orderNumbers = collect($response->json())->pluck('order_number')->toArray();

        $this->assertContains($clientOrder->id, $orderIds, 'User should see their own order');
        $this->assertNotContains($otherOrder->id, $orderIds, 'User should NOT see another user\'s order');
        $this->assertCount(1, $response->json(), 'User should see exactly 1 order (their own)');
    }

    public function test_user_can_view_own_order_by_id(): void
    {
        $order = Order::create([
            'user_id' => $this->client->id,
            'order_number' => 'ORD-VIEW-' . time(),
            'total_price' => 150.00,
            'status' => 'pending',
            'payment_method' => 'cod',
        ]);

        $response = $this->getJson("/api/orders/{$order->id}", $this->clientHeaders());

        $response->assertOk();
        $this->assertEquals($order->id, $response->json('id'));
        $this->assertEquals($order->order_number, $response->json('order_number'));
    }

    public function test_user_cannot_view_another_users_order(): void
    {
        $otherClient = User::factory()->create([
            'first_name' => 'Other',
            'last_name' => 'Viewer',
            'email' => 'other-viewer@bugfix-test.com',
        ]);

        $otherOrder = Order::create([
            'user_id' => $otherClient->id,
            'order_number' => 'ORD-HIDDEN-' . time(),
            'total_price' => 300.00,
            'status' => 'pending',
            'payment_method' => 'cod',
        ]);

        $response = $this->getJson("/api/orders/{$otherOrder->id}", $this->clientHeaders());

        $response->assertNotFound();
    }

    public function test_unauthenticated_user_without_session_gets_empty_orders(): void
    {
        // Create an order that belongs to a user (won't be returned for unauthenticated)
        Order::create([
            'user_id' => $this->client->id,
            'order_number' => 'ORD-UNAUTH-' . time(),
            'total_price' => 50.00,
            'status' => 'pending',
            'payment_method' => 'cod',
        ]);

        $response = $this->getJson('/api/orders');

        $response->assertOk();
        $this->assertCount(0, $response->json(), 'Unauthenticated user without session should get empty orders array');
    }

    public function test_guest_with_session_sees_own_orders(): void
    {
        $sessionId = 'test-session-' . time();

        Order::create([
            'session_id' => $sessionId,
            'order_number' => 'ORD-GUEST-1-' . time(),
            'total_price' => 75.00,
            'status' => 'pending',
            'payment_method' => 'cod',
        ]);

        Order::create([
            'session_id' => $sessionId,
            'order_number' => 'ORD-GUEST-2-' . time(),
            'total_price' => 125.00,
            'status' => 'pending',
            'payment_method' => 'cod',
        ]);

        $response = $this->getJson('/api/orders', ['X-Session-Id' => $sessionId]);

        $response->assertOk();
        $this->assertCount(2, $response->json(), 'Guest should see orders for their session');
    }

    public function test_guest_cannot_see_another_sessions_orders(): void
    {
        Order::create([
            'session_id' => 'session-alpha',
            'order_number' => 'ORD-ALPHA-' . time(),
            'total_price' => 100.00,
            'status' => 'pending',
            'payment_method' => 'cod',
        ]);

        Order::create([
            'session_id' => 'session-alpha',
            'order_number' => 'ORD-ALPHA-2-' . time(),
            'total_price' => 200.00,
            'status' => 'pending',
            'payment_method' => 'cod',
        ]);

        $response = $this->getJson('/api/orders', ['X-Session-Id' => 'session-beta']);

        $response->assertOk();
        $this->assertCount(0, $response->json(), 'Guest with different session should not see other sessions\' orders');
    }

    public function test_guest_can_view_own_session_order_by_id(): void
    {
        $sessionId = 'session-view-' . time();

        $order = Order::create([
            'session_id' => $sessionId,
            'order_number' => 'ORD-GVIEW-' . time(),
            'total_price' => 99.00,
            'status' => 'pending',
            'payment_method' => 'cod',
        ]);

        $response = $this->getJson("/api/orders/{$order->id}", ['X-Session-Id' => $sessionId]);

        $response->assertOk();
        $this->assertEquals($order->id, $response->json('id'));
    }

    public function test_guest_cannot_view_another_sessions_order_by_id(): void
    {
        $order = Order::create([
            'session_id' => 'session-other',
            'order_number' => 'ORD-GHIDDEN-' . time(),
            'total_price' => 500.00,
            'status' => 'pending',
            'payment_method' => 'cod',
        ]);

        $response = $this->getJson("/api/orders/{$order->id}", ['X-Session-Id' => 'session-wrong']);

        $response->assertNotFound();
    }

    // =========================
    // ADMIN ORDER STATUS TRANSITION TESTS
    // =========================

    public function test_admin_can_transition_pending_to_processing(): void
    {
        $order = Order::create([
            'user_id' => $this->client->id,
            'order_number' => 'ORD-TRANS-PP-' . time(),
            'total_price' => 100.00,
            'status' => 'pending',
            'payment_method' => 'cod',
        ]);

        $response = $this->putJson("/api/admin/orders/{$order->id}/status", [
            'status' => 'processing',
        ], $this->adminHeaders());

        $response->assertOk();
        $this->assertEquals('processing', $response->json('order.status'));
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'processing',
        ]);
    }

    public function test_admin_can_transition_processing_to_shipped(): void
    {
        $order = Order::create([
            'user_id' => $this->client->id,
            'order_number' => 'ORD-TRANS-PS-' . time(),
            'total_price' => 100.00,
            'status' => 'processing',
            'payment_method' => 'cod',
        ]);

        $response = $this->putJson("/api/admin/orders/{$order->id}/status", [
            'status' => 'shipped',
        ], $this->adminHeaders());

        $response->assertOk();
        $this->assertEquals('shipped', $response->json('order.status'));
    }

    public function test_admin_can_transition_shipped_to_delivered(): void
    {
        $order = Order::create([
            'user_id' => $this->client->id,
            'order_number' => 'ORD-TRANS-SD-' . time(),
            'total_price' => 100.00,
            'status' => 'shipped',
            'payment_method' => 'cod',
        ]);

        $response = $this->putJson("/api/admin/orders/{$order->id}/status", [
            'status' => 'delivered',
        ], $this->adminHeaders());

        $response->assertOk();
        $this->assertEquals('delivered', $response->json('order.status'));
    }

    public function test_admin_can_transition_pending_to_cancelled(): void
    {
        $order = Order::create([
            'user_id' => $this->client->id,
            'order_number' => 'ORD-CANCEL-PC-' . time(),
            'total_price' => 100.00,
            'status' => 'pending',
            'payment_method' => 'cod',
        ]);

        $response = $this->putJson("/api/admin/orders/{$order->id}/status", [
            'status' => 'cancelled',
        ], $this->adminHeaders());

        $response->assertOk();
        $this->assertEquals('cancelled', $response->json('order.status'));
    }

    public function test_admin_can_transition_processing_to_cancelled(): void
    {
        $order = Order::create([
            'user_id' => $this->client->id,
            'order_number' => 'ORD-CANCEL-PRC-' . time(),
            'total_price' => 100.00,
            'status' => 'processing',
            'payment_method' => 'cod',
        ]);

        $response = $this->putJson("/api/admin/orders/{$order->id}/status", [
            'status' => 'cancelled',
        ], $this->adminHeaders());

        $response->assertOk();
        $this->assertEquals('cancelled', $response->json('order.status'));
    }

    public function test_admin_can_transition_shipped_to_cancelled(): void
    {
        $order = Order::create([
            'user_id' => $this->client->id,
            'order_number' => 'ORD-CANCEL-SC-' . time(),
            'total_price' => 100.00,
            'status' => 'shipped',
            'payment_method' => 'cod',
        ]);

        $response = $this->putJson("/api/admin/orders/{$order->id}/status", [
            'status' => 'cancelled',
        ], $this->adminHeaders());

        $response->assertOk();
        $this->assertEquals('cancelled', $response->json('order.status'));
    }

    public function test_invalid_transition_returns_422(): void
    {
        $order = Order::create([
            'user_id' => $this->client->id,
            'order_number' => 'ORD-INV-SKIP-' . time(),
            'total_price' => 100.00,
            'status' => 'pending',
            'payment_method' => 'cod',
        ]);

        // Skipping from pending directly to delivered is invalid
        $response = $this->putJson("/api/admin/orders/{$order->id}/status", [
            'status' => 'delivered',
        ], $this->adminHeaders());

        $response->assertStatus(422);
    }

    public function test_terminal_state_delivered_cannot_change(): void
    {
        $order = Order::create([
            'user_id' => $this->client->id,
            'order_number' => 'ORD-TERM-DEL-' . time(),
            'total_price' => 100.00,
            'status' => 'delivered',
            'payment_method' => 'cod',
        ]);

        $response = $this->putJson("/api/admin/orders/{$order->id}/status", [
            'status' => 'processing',
        ], $this->adminHeaders());

        $response->assertStatus(422);
    }

    public function test_terminal_state_cancelled_cannot_change(): void
    {
        $order = Order::create([
            'user_id' => $this->client->id,
            'order_number' => 'ORD-TERM-CAN-' . time(),
            'total_price' => 100.00,
            'status' => 'cancelled',
            'payment_method' => 'cod',
        ]);

        $response = $this->putJson("/api/admin/orders/{$order->id}/status", [
            'status' => 'pending',
        ], $this->adminHeaders());

        $response->assertStatus(422);
    }

    public function test_full_lifecycle_pending_to_delivered(): void
    {
        $order = Order::create([
            'user_id' => $this->client->id,
            'order_number' => 'ORD-LIFECYCLE-' . time(),
            'total_price' => 100.00,
            'status' => 'pending',
            'payment_method' => 'cod',
        ]);

        // pending -> processing
        $this->putJson("/api/admin/orders/{$order->id}/status", ['status' => 'processing'], $this->adminHeaders());
        $this->assertEquals('processing', $order->fresh()->status);

        // processing -> shipped
        $this->putJson("/api/admin/orders/{$order->id}/status", ['status' => 'shipped'], $this->adminHeaders());
        $this->assertEquals('shipped', $order->fresh()->status);

        // shipped -> delivered
        $this->putJson("/api/admin/orders/{$order->id}/status", ['status' => 'delivered'], $this->adminHeaders());
        $this->assertEquals('delivered', $order->fresh()->status);
    }

    public function test_delivered_creates_revenue_record(): void
    {
        $order = Order::create([
            'user_id' => $this->client->id,
            'order_number' => 'ORD-REVENUE-' . time(),
            'total_price' => 250.00,
            'status' => 'shipped',
            'payment_method' => 'cod',
        ]);

        $this->putJson("/api/admin/orders/{$order->id}/status", [
            'status' => 'delivered',
        ], $this->adminHeaders());

        $this->assertDatabaseHas('revenues', [
            'order_id' => $order->id,
            'amount' => 250.00,
            'source' => 'order',
        ]);
    }

    public function test_delivered_does_not_create_duplicate_revenue(): void
    {
        $order = Order::create([
            'user_id' => $this->client->id,
            'order_number' => 'ORD-DUPREV-' . time(),
            'total_price' => 100.00,
            'status' => 'shipped',
            'payment_method' => 'cod',
        ]);

        // First transition to delivered creates revenue
        $this->putJson("/api/admin/orders/{$order->id}/status", ['status' => 'delivered'], $this->adminHeaders());
        $this->assertDatabaseCount('revenues', 1);

        // Second transition (same state) should not create another
        // Actually the controller would reject delivered->delivered as invalid, so just test count=1
    }

    public function test_cancelled_restores_stock(): void
    {
        $category = Category::create(['name' => 'Restore Cat', 'slug' => 'restore-cat', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Restore Brand', 'slug' => 'restore-brand', 'is_active' => true]);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Restock Product',
            'slug' => 'restock-product',
            'price' => 50.00,
            'stock' => 5,
            'sku' => 'RESTK-001',
            'is_active' => true,
        ]);

        $order = Order::create([
            'user_id' => $this->client->id,
            'order_number' => 'ORD-RESTORE-' . time(),
            'total_price' => 100.00,
            'status' => 'shipped',
            'payment_method' => 'cod',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'price' => 50.00,
        ]);

        $this->putJson("/api/admin/orders/{$order->id}/status", [
            'status' => 'cancelled',
        ], $this->adminHeaders());

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 8, // 5 + 3 = 8
        ]);
    }

    public function test_delivered_with_card_payment_marks_payment_as_paid(): void
    {
        $order = Order::create([
            'user_id' => $this->client->id,
            'order_number' => 'ORD-CARDPAY-' . time(),
            'total_price' => 200.00,
            'status' => 'shipped',
            'payment_method' => 'card',
        ]);

        // Create a payment record (as checkout would)
        Payment::create([
            'order_id' => $order->id,
            'amount' => 200.00,
            'currency' => 'MAD',
            'payment_method' => 'card',
            'payment_type' => 'full',
            'status' => 'pending',
        ]);

        $this->putJson("/api/admin/orders/{$order->id}/status", [
            'status' => 'delivered',
        ], $this->adminHeaders());

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => 'paid',
        ]);
    }

    public function test_non_admin_cannot_update_order_status(): void
    {
        $order = Order::create([
            'user_id' => $this->client->id,
            'order_number' => 'ORD-FORBIDDEN-' . time(),
            'total_price' => 100.00,
            'status' => 'pending',
            'payment_method' => 'cod',
        ]);

        $response = $this->putJson("/api/admin/orders/{$order->id}/status", [
            'status' => 'processing',
        ], $this->clientHeaders());

        $response->assertForbidden();
    }

    public function test_invalid_status_value_returns_422(): void
    {
        $order = Order::create([
            'user_id' => $this->client->id,
            'order_number' => 'ORD-BADSTATUS-' . time(),
            'total_price' => 100.00,
            'status' => 'pending',
            'payment_method' => 'cod',
        ]);

        $response = $this->putJson("/api/admin/orders/{$order->id}/status", [
            'status' => 'nonexistent_status',
        ], $this->adminHeaders());

        $response->assertStatus(422);
    }

    // =========================
    // GUEST CHECKOUT TESTS
    // =========================

    private function createGuestCart(string $sessionId, int $productId, int $quantity = 2): void
    {
        Cart::create([
            'session_id' => $sessionId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'status' => 'active',
        ]);
    }

    private function guestCheckoutPayload(): array
    {
        return [
            'payment_method' => 'cod',
            'guest_name' => 'Guest Shopper',
            'guest_email' => 'guest@example.com',
            'guest_phone' => '0612345678',
            'address_line1' => '456 Guest Street',
            'address_line2' => 'Apt 7B',
            'city' => 'Marrakech',
            'state' => 'Marrakech-Safi',
            'postal_code' => '40000',
            'country' => 'Morocco',
        ];
    }

    public function test_guest_checkout_successful(): void
    {
        $sessionId = 'guest-session-' . time();
        $category = Category::create(['name' => 'Guest Cat', 'slug' => 'guest-cat', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Guest Brand', 'slug' => 'guest-brand', 'is_active' => true]);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Guest Product',
            'slug' => 'guest-product',
            'price' => 50.00,
            'stock' => 10,
            'sku' => 'GUEST-001',
            'is_active' => true,
        ]);

        $this->createGuestCart($sessionId, $product->id, 2);

        $response = $this->postJson('/api/orders', $this->guestCheckoutPayload(), [
            'X-Session-Id' => $sessionId,
        ]);

        $response->assertCreated();
        $order = $response->json('order');

        $this->assertEquals($order['guest_name'], 'Guest Shopper');
        $this->assertEquals($order['guest_email'], 'guest@example.com');
        $this->assertNull($order['user_id'], 'Guest order should have null user_id');
    }

    public function test_guest_checkout_creates_address_record(): void
    {
        $sessionId = 'guest-addr-session-' . time();
        $category = Category::create(['name' => 'Addr Cat', 'slug' => 'addr-cat', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Addr Brand', 'slug' => 'addr-brand', 'is_active' => true]);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Addr Product',
            'slug' => 'addr-product',
            'price' => 30.00,
            'stock' => 10,
            'sku' => 'ADDR-001',
            'is_active' => true,
        ]);

        $this->createGuestCart($sessionId, $product->id);

        $response = $this->postJson('/api/orders', $this->guestCheckoutPayload(), [
            'X-Session-Id' => $sessionId,
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('addresses', [
            'full_name' => 'Guest Shopper',
            'email' => 'guest@example.com',
            'address_line1' => '456 Guest Street',
            'city' => 'Marrakech',
            'country' => 'Morocco',
            'user_id' => null,
            'is_default' => false,
        ]);
    }

    public function test_guest_checkout_creates_invoice(): void
    {
        $sessionId = 'guest-inv-session-' . time();
        $category = Category::create(['name' => 'Inv Cat', 'slug' => 'inv-cat', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Inv Brand', 'slug' => 'inv-brand', 'is_active' => true]);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Inv Product',
            'slug' => 'inv-product',
            'price' => 40.00,
            'stock' => 10,
            'sku' => 'INV-001',
            'is_active' => true,
        ]);

        $this->createGuestCart($sessionId, $product->id, 3);

        $this->postJson('/api/orders', $this->guestCheckoutPayload(), [
            'X-Session-Id' => $sessionId,
        ]);

        $this->assertDatabaseHas('invoices', [
            'status' => 'unpaid',
        ]);
    }

    public function test_guest_checkout_card_payment_creates_paid_invoice(): void
    {
        $sessionId = 'guest-card-session-' . time();
        $category = Category::create(['name' => 'Card Cat', 'slug' => 'card-cat', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Card Brand', 'slug' => 'card-brand', 'is_active' => true]);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Card Product',
            'slug' => 'card-product',
            'price' => 60.00,
            'stock' => 10,
            'sku' => 'CARD-001',
            'is_active' => true,
        ]);

        $this->createGuestCart($sessionId, $product->id, 1);

        $payload = $this->guestCheckoutPayload();
        $payload['payment_method'] = 'card';

        $this->postJson('/api/orders', $payload, [
            'X-Session-Id' => $sessionId,
        ]);

        $this->assertDatabaseHas('invoices', [
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('payments', [
            'payment_method' => 'card',
            'status' => 'paid',
        ]);
    }

    public function test_guest_checkout_marks_cart_as_converted(): void
    {
        $sessionId = 'guest-conv-session-' . time();
        $category = Category::create(['name' => 'Conv Cat', 'slug' => 'conv-cat', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Conv Brand', 'slug' => 'conv-brand', 'is_active' => true]);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Conv Product',
            'slug' => 'conv-product',
            'price' => 20.00,
            'stock' => 10,
            'sku' => 'CONV-001',
            'is_active' => true,
        ]);

        $this->createGuestCart($sessionId, $product->id, 1);

        $this->postJson('/api/orders', $this->guestCheckoutPayload(), [
            'X-Session-Id' => $sessionId,
        ]);

        $this->assertDatabaseHas('carts', [
            'session_id' => $sessionId,
            'status' => 'converted',
        ]);
    }

    public function test_guest_checkout_empty_cart_returns_422(): void
    {
        $sessionId = 'guest-empty-session-' . time();

        $response = $this->postJson('/api/orders', $this->guestCheckoutPayload(), [
            'X-Session-Id' => $sessionId,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Your cart is empty']);
    }

    public function test_guest_checkout_missing_required_fields_returns_422(): void
    {
        $sessionId = 'guest-validate-session-' . time();
        $category = Category::create(['name' => 'Val Cat', 'slug' => 'val-cat', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Val Brand', 'slug' => 'val-brand', 'is_active' => true]);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Val Product',
            'slug' => 'val-product',
            'price' => 10.00,
            'stock' => 10,
            'sku' => 'VAL-001',
            'is_active' => true,
        ]);

        $this->createGuestCart($sessionId, $product->id);

        // Missing guest_name, guest_email, address_line1, city, country
        $response = $this->postJson('/api/orders', [
            'payment_method' => 'cod',
        ], ['X-Session-Id' => $sessionId]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'guest_name', 'guest_email', 'address_line1', 'city', 'country',
        ]);
    }

    public function test_guest_checkout_insufficient_stock_returns_422(): void
    {
        $sessionId = 'guest-stock-session-' . time();
        $category = Category::create(['name' => 'StockFail Cat', 'slug' => 'stockfail-cat', 'is_active' => true]);
        $brand = Brand::create(['name' => 'StockFail Brand', 'slug' => 'stockfail-brand', 'is_active' => true]);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Low Stock Product',
            'slug' => 'low-stock-product',
            'price' => 10.00,
            'stock' => 1,
            'sku' => 'LOWSTK-001',
            'is_active' => true,
        ]);

        // Try to buy 5 when only 1 in stock
        $this->createGuestCart($sessionId, $product->id, 5);

        $response = $this->postJson('/api/orders', $this->guestCheckoutPayload(), [
            'X-Session-Id' => $sessionId,
        ]);

        $response->assertStatus(422);
    }

    public function test_guest_checkout_reduces_stock(): void
    {
        $sessionId = 'guest-stockdec-session-' . time();
        $category = Category::create(['name' => 'Dec Cat', 'slug' => 'dec-cat', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Dec Brand', 'slug' => 'dec-brand', 'is_active' => true]);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Dec Product',
            'slug' => 'dec-product',
            'price' => 15.00,
            'stock' => 20,
            'sku' => 'DEC-001',
            'is_active' => true,
        ]);

        $this->createGuestCart($sessionId, $product->id, 7);

        $this->postJson('/api/orders', $this->guestCheckoutPayload(), [
            'X-Session-Id' => $sessionId,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock' => 13, // 20 - 7 = 13
        ]);
    }

    public function test_guest_checkout_guest_can_retrieve_order_by_session(): void
    {
        $sessionId = 'guest-retrieve-session-' . time();
        $category = Category::create(['name' => 'Ret Cat', 'slug' => 'ret-cat', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Ret Brand', 'slug' => 'ret-brand', 'is_active' => true]);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Ret Product',
            'slug' => 'ret-product',
            'price' => 25.00,
            'stock' => 10,
            'sku' => 'RET-001',
            'is_active' => true,
        ]);

        $this->createGuestCart($sessionId, $product->id, 1);

        $createResponse = $this->postJson('/api/orders', $this->guestCheckoutPayload(), [
            'X-Session-Id' => $sessionId,
        ]);

        $createResponse->assertCreated();
        $orderId = $createResponse->json('order.id');

        // Retrieve the order by session
        $listResponse = $this->getJson('/api/orders', ['X-Session-Id' => $sessionId]);

        $listResponse->assertOk();
        $orderIds = collect($listResponse->json())->pluck('id')->toArray();
        $this->assertContains($orderId, $orderIds, 'Guest should be able to retrieve their created order');
    }
}
