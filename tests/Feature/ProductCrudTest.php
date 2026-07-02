<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Review;
use App\Models\Wishlist;
use App\Models\Expense;
use App\Models\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end tests for Product CRUD operations.
 *
 * Covers: Create, Read (list), Update, Delete, validation,
 * authentication guards, authorization guards, and full lifecycle.
 */
class ProductCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $client;
    private Category $category;
    private Brand $brand;
    private array $adminHeaders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->client = User::factory()->create(['role' => 'client']);

        $this->category = Category::factory()->create();
        $this->brand = Brand::factory()->create();

        $this->adminHeaders = [
            'Authorization' => 'Bearer ' . $this->admin->createToken('test')->plainTextToken,
            'Accept' => 'application/json',
        ];
    }

    private function clientHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->client->createToken('test')->plainTextToken,
            'Accept' => 'application/json',
        ];
    }

    // ====================================================================
    // AUTHENTICATION & AUTHORIZATION GUARDS
    // ====================================================================

    public function test_unauthenticated_user_cannot_access_admin_product_endpoints(): void
    {
        $this->getJson('/api/admin/products')->assertUnauthorized();
        $this->postJson('/api/admin/products', [])->assertUnauthorized();
        $this->putJson('/api/admin/products/1', [])->assertUnauthorized();
        $this->deleteJson('/api/admin/products/1')->assertUnauthorized();
    }

    public function test_non_admin_user_cannot_access_admin_product_endpoints(): void
    {
        $this->getJson('/api/admin/products', $this->clientHeaders())->assertForbidden();
        $this->postJson('/api/admin/products', [], $this->clientHeaders())->assertForbidden();
        $this->putJson('/api/admin/products/1', [], $this->clientHeaders())->assertForbidden();
        $this->deleteJson('/api/admin/products/1', $this->clientHeaders())->assertForbidden();
    }

    // ====================================================================
    // CREATE
    // ====================================================================

    public function test_admin_can_create_product(): void
    {
        $response = $this->postJson('/api/admin/products', [
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'name' => 'Test Product CRUD',
            'price' => 99.99,
            'stock' => 25,
            'sku' => 'CRUD-001',
            'description' => 'Created during CRUD test',
        ], $this->adminHeaders);

        $response->assertStatus(201)
            ->assertJson(['message' => 'Product created successfully']);

        $this->assertDatabaseHas('products', [
            'name' => 'Test Product CRUD',
            'slug' => 'test-product-crud',
            'price' => 99.99,
            'stock' => 25,
            'sku' => 'CRUD-001',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_create_product_with_minimal_fields(): void
    {
        $response = $this->postJson('/api/admin/products', [
            'category_id' => $this->category->id,
            'name' => 'Minimal Product',
            'price' => 10.00,
        ], $this->adminHeaders);

        $response->assertStatus(201);
        $this->assertDatabaseHas('products', [
            'name' => 'Minimal Product',
            'price' => 10.00,
            'stock' => 0,
            'is_active' => true,
        ]);
    }

    public function test_create_product_validates_required_fields(): void
    {
        $response = $this->postJson('/api/admin/products', [], $this->adminHeaders);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id', 'name', 'price']);
    }

    public function test_create_product_validates_name_max_length(): void
    {
        $response = $this->postJson('/api/admin/products', [
            'category_id' => $this->category->id,
            'name' => str_repeat('A', 256),
            'price' => 10.00,
        ], $this->adminHeaders);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_product_validates_unique_sku(): void
    {
        Product::factory()->create(['sku' => 'DUPSKU']);

        $response = $this->postJson('/api/admin/products', [
            'category_id' => $this->category->id,
            'name' => 'Duplicate SKU',
            'price' => 10.00,
            'sku' => 'DUPSKU',
        ], $this->adminHeaders);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sku']);
    }

    public function test_create_product_validates_price_non_negative(): void
    {
        $response = $this->postJson('/api/admin/products', [
            'category_id' => $this->category->id,
            'name' => 'Negative Price',
            'price' => -5.00,
        ], $this->adminHeaders);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['price']);
    }

    public function test_create_product_validates_category_exists(): void
    {
        $response = $this->postJson('/api/admin/products', [
            'category_id' => 99999,
            'name' => 'Bad Category',
            'price' => 10.00,
        ], $this->adminHeaders);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_create_product_generates_slug_from_name(): void
    {
        $response = $this->postJson('/api/admin/products', [
            'category_id' => $this->category->id,
            'name' => 'My Unique Product Name',
            'price' => 25.00,
        ], $this->adminHeaders);

        $response->assertStatus(201);
        $this->assertEquals('my-unique-product-name', $response->json('product.slug'));
    }

    public function test_create_product_auto_creates_expense_for_purchased_stock(): void
    {
        $response = $this->postJson('/api/admin/products', [
            'category_id' => $this->category->id,
            'name' => 'Stocked Product',
            'price' => 50.00,
            'purchase_price' => 20.00,
            'stock' => 10,
        ], $this->adminHeaders);

        $response->assertStatus(201);
        $productId = $response->json('product.id');

        $this->assertDatabaseHas('expenses', [
            'product_id' => $productId,
            'category' => 'products',
            'amount' => 200.00, // 20 * 10
        ]);
    }

    // ====================================================================
    // LIST
    // ====================================================================

    public function test_admin_can_list_products(): void
    {
        Product::factory()->count(3)->create();

        $response = $this->getJson('/api/admin/products', $this->adminHeaders);

        $response->assertOk()
            ->assertJsonStructure(['data', 'current_page', 'last_page', 'total']);
        $this->assertEquals(3, $response->json('total'));
    }

    public function test_admin_list_returns_paginated_results(): void
    {
        Product::factory()->count(25)->create();

        $response = $this->getJson('/api/admin/products?per_page=10', $this->adminHeaders);

        $response->assertOk();
        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(3, $response->json('last_page'));
    }

    public function test_admin_can_filter_products_by_search(): void
    {
        Product::factory()->create(['name' => 'Special Widget']);
        Product::factory()->create(['name' => 'Regular Gadget']);
        Product::factory()->create(['name' => 'Another Item']);

        $response = $this->getJson('/api/admin/products?search=widget', $this->adminHeaders);

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Special Widget', $response->json('data.0.name'));
    }

    public function test_admin_can_filter_products_by_category(): void
    {
        $otherCategory = Category::factory()->create();

        Product::factory()->create(['category_id' => $this->category->id]);
        Product::factory()->create(['category_id' => $otherCategory->id]);

        $response = $this->getJson('/api/admin/products?category_id=' . $this->category->id, $this->adminHeaders);

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_admin_can_filter_products_by_active_status(): void
    {
        Product::factory()->create(['is_active' => true]);
        Product::factory()->count(2)->create(['is_active' => false]);

        $response = $this->getJson('/api/admin/products?is_active=0', $this->adminHeaders);

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_admin_list_includes_inactive_and_out_of_stock_products(): void
    {
        Product::factory()->create(['is_active' => false, 'stock' => 0]);

        $response = $this->getJson('/api/admin/products', $this->adminHeaders);

        $response->assertOk();
        $this->assertEquals(1, $response->json('total'));
    }

    public function test_admin_can_sort_products_by_price_ascending(): void
    {
        Product::factory()->create(['name' => 'Cheap', 'price' => 5.00]);
        Product::factory()->create(['name' => 'Expensive', 'price' => 100.00]);
        Product::factory()->create(['name' => 'Mid', 'price' => 50.00]);

        $response = $this->getJson('/api/admin/products?sort=price_asc', $this->adminHeaders);

        $response->assertOk();
        $prices = collect($response->json('data'))->pluck('price')->toArray();
        $this->assertEquals([5.00, 50.00, 100.00], $prices);
    }

    public function test_admin_can_sort_products_by_price_descending(): void
    {
        Product::factory()->create(['name' => 'Cheap', 'price' => 5.00]);
        Product::factory()->create(['name' => 'Expensive', 'price' => 100.00]);

        $response = $this->getJson('/api/admin/products?sort=price_desc', $this->adminHeaders);

        $response->assertOk();
        $prices = collect($response->json('data'))->pluck('price')->toArray();
        $this->assertEquals([100.00, 5.00], $prices);
    }

    public function test_admin_list_returns_empty_when_no_products_exist(): void
    {
        $response = $this->getJson('/api/admin/products', $this->adminHeaders);

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
        $this->assertEquals(0, $response->json('total'));
    }

    // ====================================================================
    // UPDATE
    // ====================================================================

    public function test_admin_can_update_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->putJson('/api/admin/products/' . $product->id, [
            'name' => 'Updated Name',
            'price' => 149.99,
            'stock' => 50,
        ], $this->adminHeaders);

        $response->assertOk()
            ->assertJson(['message' => 'Product updated successfully']);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Name',
            'slug' => 'updated-name',
            'price' => 149.99,
            'stock' => 50,
        ]);
    }

    public function test_admin_can_update_single_field(): void
    {
        $product = Product::factory()->create(['name' => 'Original Name', 'price' => 25.00]);

        $response = $this->putJson('/api/admin/products/' . $product->id, [
            'price' => 35.00,
        ], $this->adminHeaders);

        $response->assertOk();
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Original Name', // unchanged
            'price' => 35.00,
        ]);
    }

    public function test_update_product_validates_unique_sku_excluding_self(): void
    {
        $product = Product::factory()->create(['sku' => 'ORIGSKU']);
        $other = Product::factory()->create(['sku' => 'OTHERSKU']);

        // Updating own SKU to same value should succeed
        $response = $this->putJson('/api/admin/products/' . $product->id, [
            'sku' => 'ORIGSKU',
        ], $this->adminHeaders);
        $response->assertOk();

        // Updating to another product's SKU should fail
        $response = $this->putJson('/api/admin/products/' . $product->id, [
            'sku' => 'OTHERSKU',
        ], $this->adminHeaders);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sku']);
    }

    public function test_update_product_can_deactivate(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $response = $this->putJson('/api/admin/products/' . $product->id, [
            'is_active' => false,
        ], $this->adminHeaders);

        $response->assertOk();
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'is_active' => false,
        ]);
    }

    public function test_update_product_can_feature(): void
    {
        $product = Product::factory()->create(['featured' => false]);

        $response = $this->putJson('/api/admin/products/' . $product->id, [
            'featured' => true,
        ], $this->adminHeaders);

        $response->assertOk();
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'featured' => true,
        ]);
    }

    public function test_update_product_returns_404_for_nonexistent(): void
    {
        $response = $this->putJson('/api/admin/products/99999', [
            'name' => 'Ghost',
        ], $this->adminHeaders);

        $response->assertStatus(404);
    }

    // ====================================================================
    // DELETE
    // ====================================================================

    public function test_admin_can_delete_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->deleteJson('/api/admin/products/' . $product->id, [], $this->adminHeaders);

        $response->assertOk()
            ->assertJson(['message' => 'Product deleted successfully']);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_delete_product_returns_404_for_nonexistent(): void
    {
        $response = $this->deleteJson('/api/admin/products/99999', [], $this->adminHeaders);

        $response->assertStatus(404);
    }

    // ====================================================================
    // REFERENCES CHECK
    // ====================================================================

    public function test_references_returns_zero_for_product_with_no_references(): void
    {
        $product = Product::factory()->create();

        $response = $this->getJson('/api/admin/products/' . $product->id . '/references', $this->adminHeaders);

        $response->assertOk()
            ->assertJson([
                'has_references' => false,
                'references' => [
                    'orders'    => 0,
                    'invoices'  => 0,
                    'reviews'   => 0,
                    'wishlists' => 0,
                    'carts'     => 0,
                    'expenses'  => 0,
                    'coupons'   => 0,
                ],
            ]);
    }

    public function test_references_shows_positive_counts_when_product_is_referenced(): void
    {
        $product = Product::factory()->create();

        // Create a review for this product
        Review::factory()->create(['product_id' => $product->id]);

        // Add to wishlist (directly, no factory)
        Wishlist::create([
            'user_id'    => $this->admin->id,
            'product_id' => $product->id,
        ]);

        // Add expense
        Expense::factory()->create(['product_id' => $product->id]);

        // Add cart item
        Cart::create([
            'user_id'    => $this->admin->id,
            'product_id' => $product->id,
            'quantity'   => 1,
        ]);

        $response = $this->getJson('/api/admin/products/' . $product->id . '/references', $this->adminHeaders);

        $response->assertOk()
            ->assertJson([
                'has_references' => true,
            ]);

        $refs = $response->json('references');
        $this->assertEquals(1, $refs['reviews']);
        $this->assertEquals(1, $refs['wishlists']);
        $this->assertEquals(1, $refs['expenses']);
        $this->assertEquals(1, $refs['carts']);
    }

    public function test_delete_without_force_returns_409_when_references_exist(): void
    {
        $product = Product::factory()->create();

        // Add a review to create a reference
        Review::factory()->create(['product_id' => $product->id]);

        $response = $this->deleteJson('/api/admin/products/' . $product->id, [], $this->adminHeaders);

        $response->assertStatus(409)
            ->assertJson(['has_references' => true]);

        // Product should still exist
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_delete_with_force_succeeds_even_with_references(): void
    {
        $product = Product::factory()->create();

        // Add a review to create a reference
        Review::factory()->create(['product_id' => $product->id]);

        $response = $this->deleteJson('/api/admin/products/' . $product->id . '?force=1', [], $this->adminHeaders);

        $response->assertOk()
            ->assertJson(['message' => 'Product deleted successfully']);

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    // ====================================================================
    // FULL LIFECYCLE
    // ====================================================================

    public function test_full_product_lifecycle(): void
    {
        // 1. CREATE
        $createResponse = $this->postJson('/api/admin/products', [
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'name' => 'Lifecycle Product',
            'price' => 75.00,
            'stock' => 30,
            'sku' => 'LIFE-001',
            'description' => 'Full lifecycle test',
        ], $this->adminHeaders);

        $createResponse->assertStatus(201);
        $productId = $createResponse->json('product.id');
        $this->assertNotNull($productId);
        $this->assertEquals('Lifecycle Product', $createResponse->json('product.name'));

        // 2. LIST — verify the product appears
        $listResponse = $this->getJson('/api/admin/products', $this->adminHeaders);
        $listResponse->assertOk();
        $this->assertGreaterThanOrEqual(1, $listResponse->json('total'));
        $found = collect($listResponse->json('data'))->firstWhere('id', $productId);
        $this->assertNotNull($found);
        $this->assertEquals('Lifecycle Product', $found['name']);

        // 3. UPDATE
        $updateResponse = $this->putJson('/api/admin/products/' . $productId, [
            'name' => 'Updated Lifecycle Product',
            'price' => 89.99,
            'is_active' => false,
        ], $this->adminHeaders);

        $updateResponse->assertOk();
        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'name' => 'Updated Lifecycle Product',
            'price' => 89.99,
            'is_active' => false,
        ]);

        // 4. LIST after update — verify changes reflected
        $updatedListResponse = $this->getJson('/api/admin/products', $this->adminHeaders);
        $updatedListResponse->assertOk();
        $updated = collect($updatedListResponse->json('data'))->firstWhere('id', $productId);
        $this->assertNotNull($updated);
        $this->assertEquals('Updated Lifecycle Product', $updated['name']);
        $this->assertEquals(false, $updated['is_active']);

        // 5. DELETE
        $deleteResponse = $this->deleteJson('/api/admin/products/' . $productId, [], $this->adminHeaders);
        $deleteResponse->assertOk();

        $this->assertDatabaseMissing('products', ['id' => $productId]);

        // 6. LIST after delete — verify removed
        $finalListResponse = $this->getJson('/api/admin/products', $this->adminHeaders);
        $finalListResponse->assertOk();
        $finalFound = collect($finalListResponse->json('data'))->firstWhere('id', $productId);
        $this->assertNull($finalFound);
    }
}
