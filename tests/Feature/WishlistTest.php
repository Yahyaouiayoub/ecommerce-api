<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $otherUser;
    private string $userToken;
    private string $otherUserToken;
    private Product $product;
    private Product $product2;

    protected function setUp(): void
    {
        parent::setUp();

        // Create users
        $this->user = User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'user@test.com',
        ]);

        $this->otherUser = User::factory()->create([
            'first_name' => 'Other',
            'last_name' => 'User',
            'email' => 'other@test.com',
        ]);

        // Create API tokens
        $this->userToken = $this->user->createToken('test')->plainTextToken;
        $this->otherUserToken = $this->otherUser->createToken('test')->plainTextToken;

        // Create category and brand
        $category = Category::create(['name' => 'Test Category', 'slug' => 'test-category']);
        $brand = Brand::create(['name' => 'Test Brand', 'slug' => 'test-brand']);

        // Create products
        $this->product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Wishlist Product 1',
            'slug' => 'wishlist-product-1',
            'price' => 50.00,
            'stock' => 10,
            'sku' => 'WLP-001',
            'is_active' => true,
            'thumbnail' => '/test.jpg',
        ]);

        $this->product2 = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Wishlist Product 2',
            'slug' => 'wishlist-product-2',
            'price' => 75.00,
            'stock' => 5,
            'sku' => 'WLP-002',
            'is_active' => true,
            'thumbnail' => '/test2.jpg',
        ]);
    }

    // =========================
    // AUTH HEADER HELPERS
    // =========================

    private function userHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->userToken];
    }

    private function otherUserHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->otherUserToken];
    }

    // =========================
    // AUTHENTICATION GUARD
    // =========================

    public function test_unauthenticated_user_cannot_access_wishlist(): void
    {
        $response = $this->getJson('/api/wishlist');
        $response->assertUnauthorized();

        $response = $this->postJson('/api/wishlist/' . $this->product->id);
        $response->assertUnauthorized();

        $response = $this->deleteJson('/api/wishlist/' . $this->product->id);
        $response->assertUnauthorized();
    }

    // =========================
    // GET /api/wishlist
    // =========================

    public function test_empty_wishlist_returns_empty_array(): void
    {
        $response = $this->getJson('/api/wishlist', $this->userHeaders());

        $response->assertOk()
            ->assertJson([
                'wishlist' => [],
                'total' => 0,
            ]);
    }

    public function test_wishlist_returns_saved_items_with_product_details(): void
    {
        // Add two products
        Wishlist::create(['user_id' => $this->user->id, 'product_id' => $this->product->id]);
        Wishlist::create(['user_id' => $this->user->id, 'product_id' => $this->product2->id]);

        $response = $this->getJson('/api/wishlist', $this->userHeaders());

        $response->assertOk()
            ->assertJsonStructure([
                'wishlist' => [
                    '*' => [
                        'id',
                        'user_id',
                        'product_id',
                        'created_at',
                        'updated_at',
                        'product' => [
                            'id',
                            'name',
                            'slug',
                            'price',
                            'stock',
                            'category',
                            'brand',
                        ],
                    ],
                ],
                'total',
            ]);

        $this->assertCount(2, $response->json('wishlist'));
        $this->assertEquals(2, $response->json('total'));
        $this->assertEquals('Wishlist Product 1', $response->json('wishlist.0.product.name'));
    }

    public function test_wishlist_is_ordered_by_latest_first(): void
    {
        // Add product2 first, then product1 — product1 should be first in response
        Wishlist::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product2->id,
            'created_at' => now()->subHour(),
        ]);
        Wishlist::create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/wishlist', $this->userHeaders());

        $response->assertOk();
        // Most recent should be first
        $this->assertEquals($this->product->id, $response->json('wishlist.0.product_id'));
    }

    public function test_user_cannot_see_other_users_wishlist_items(): void
    {
        // Add item for user1
        Wishlist::create(['user_id' => $this->user->id, 'product_id' => $this->product->id]);
        // Add item for user2
        Wishlist::create(['user_id' => $this->otherUser->id, 'product_id' => $this->product2->id]);

        // user1 should only see their own item
        $response = $this->getJson('/api/wishlist', $this->userHeaders());

        $response->assertOk();
        $this->assertCount(1, $response->json('wishlist'));
        $this->assertEquals($this->product->id, $response->json('wishlist.0.product_id'));
        $this->assertEquals(1, $response->json('total'));
    }

    // =========================
    // POST /api/wishlist/{product}
    // =========================

    public function test_user_can_add_product_to_wishlist(): void
    {
        $response = $this->postJson('/api/wishlist/' . $this->product->id, [], $this->userHeaders());

        $response->assertCreated()
            ->assertJson([
                'message' => 'Product added to wishlist.',
            ])
            ->assertJsonStructure([
                'message',
                'wishlist' => [
                    'id',
                    'user_id',
                    'product_id',
                    'product' => [
                        'id',
                        'name',
                        'slug',
                    ],
                ],
            ]);

        $this->assertEquals($this->user->id, $response->json('wishlist.user_id'));
        $this->assertEquals($this->product->id, $response->json('wishlist.product_id'));

        $this->assertDatabaseHas('wishlists', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ]);
    }

    public function test_adding_duplicate_product_returns_existing_item(): void
    {
        // First add
        $this->postJson('/api/wishlist/' . $this->product->id, [], $this->userHeaders());

        // Second add — should return existing item with 200 status
        $response = $this->postJson('/api/wishlist/' . $this->product->id, [], $this->userHeaders());

        $response->assertOk()
            ->assertJson([
                'message' => 'Product is already in your wishlist.',
            ]);

        // Ensure only one wishlist record exists
        $this->assertDatabaseCount('wishlists', 1);
    }

    public function test_adding_nonexistent_product_returns_404(): void
    {
        $response = $this->postJson('/api/wishlist/99999', [], $this->userHeaders());

        $response->assertNotFound();
    }

    // =========================
    // DELETE /api/wishlist/{product}
    // =========================

    public function test_user_can_remove_product_from_wishlist(): void
    {
        // First add
        Wishlist::create(['user_id' => $this->user->id, 'product_id' => $this->product->id]);

        // Then remove
        $response = $this->deleteJson('/api/wishlist/' . $this->product->id, [], $this->userHeaders());

        $response->assertOk()
            ->assertJson([
                'message' => 'Product removed from wishlist.',
            ]);

        $this->assertDatabaseMissing('wishlists', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ]);
    }

    public function test_removing_nonexistent_wishlist_item_returns_404(): void
    {
        $response = $this->deleteJson('/api/wishlist/' . $this->product->id, [], $this->userHeaders());

        $response->assertNotFound()
            ->assertJson([
                'message' => 'Product not found in your wishlist.',
            ]);
    }

    public function test_user_cannot_remove_other_users_wishlist_item(): void
    {
        // user1 adds a product
        Wishlist::create(['user_id' => $this->user->id, 'product_id' => $this->product->id]);

        // user2 tries to remove it
        $response = $this->deleteJson('/api/wishlist/' . $this->product->id, [], $this->otherUserHeaders());

        // user2 doesn't have this product in their wishlist, so 404
        $response->assertNotFound();

        // user1's wishlist still intact
        $this->assertDatabaseHas('wishlists', [
            'user_id' => $this->user->id,
            'product_id' => $this->product->id,
        ]);
    }

    public function test_removing_validates_product_exists(): void
    {
        $response = $this->deleteJson('/api/wishlist/99999', [], $this->userHeaders());

        $response->assertNotFound();
    }

    // =========================
    // FULL WORKFLOW TESTS
    // =========================

    public function test_full_wishlist_lifecycle(): void
    {
        // 1. Start empty
        $this->getJson('/api/wishlist', $this->userHeaders())
            ->assertJson(['total' => 0]);

        // 2. Add first product
        $this->postJson('/api/wishlist/' . $this->product->id, [], $this->userHeaders())
            ->assertCreated();
        $this->assertDatabaseCount('wishlists', 1);

        // 3. Add second product
        $this->postJson('/api/wishlist/' . $this->product2->id, [], $this->userHeaders())
            ->assertCreated();
        $this->assertDatabaseCount('wishlists', 2);

        // 4. List should show 2 items
        $this->getJson('/api/wishlist', $this->userHeaders())
            ->assertJson(['total' => 2]);

        // 5. Duplicate add returns existing
        $this->postJson('/api/wishlist/' . $this->product->id, [], $this->userHeaders())
            ->assertOk();
        $this->assertDatabaseCount('wishlists', 2); // Still 2

        // 6. Remove first product
        $this->deleteJson('/api/wishlist/' . $this->product->id, [], $this->userHeaders())
            ->assertOk();
        $this->assertDatabaseCount('wishlists', 1);

        // 7. Remove non-existent returns 404
        $this->deleteJson('/api/wishlist/' . $this->product->id, [], $this->userHeaders())
            ->assertNotFound();

        // 8. List should show 1 item
        $this->getJson('/api/wishlist', $this->userHeaders())
            ->assertJson(['total' => 1]);
    }

    // =========================
    // EDGE CASES
    // =========================

    public function test_products_are_eager_loaded_with_details(): void
    {
        Wishlist::create(['user_id' => $this->user->id, 'product_id' => $this->product->id]);

        $response = $this->getJson('/api/wishlist', $this->userHeaders());

        $response->assertOk();
        $product = $response->json('wishlist.0.product');

        // Verify eager loaded relationships exist
        $this->assertArrayHasKey('category', $product);
        $this->assertArrayHasKey('brand', $product);
        $this->assertArrayHasKey('images', $product);
    }

    public function test_wishlist_still_returns_inactive_products(): void
    {
        // Inactive products that are already wishlisted should still appear
        $category = Category::first();
        $inactiveProduct = Product::create([
            'category_id' => $category->id,
            'name' => 'Inactive Product',
            'slug' => 'inactive-product',
            'price' => 30.00,
            'stock' => 0,
            'sku' => 'INACTIVE-001',
            'is_active' => false,
        ]);

        Wishlist::create([
            'user_id' => $this->user->id,
            'product_id' => $inactiveProduct->id,
        ]);

        $response = $this->getJson('/api/wishlist', $this->userHeaders());

        // Product is returned (it's in the wishlist) but note it's inactive
        $response->assertOk();
        $this->assertCount(1, $response->json('wishlist'));
        $this->assertFalse((bool) $response->json('wishlist.0.product.is_active'));
    }
}
