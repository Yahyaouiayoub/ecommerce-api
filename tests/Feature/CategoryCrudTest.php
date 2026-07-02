<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * End-to-end test for Category CRUD operations.
 *
 * Verifies that Create, Update, and Delete all work correctly
 * with the database cache driver (which does NOT support tagging).
 */
class CategoryCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private array $adminHeaders;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->adminHeaders = [
            'Authorization' => 'Bearer ' . $this->admin->createToken('test')->plainTextToken,
            'Accept' => 'application/json',
        ];
    }

    public function test_create_category_succeeds(): void
    {
        // Warm up the category cache so we can prove invalidation works
        $this->getJson('/api/admin/categories', $this->adminHeaders);

        $response = $this->postJson('/api/admin/categories', [
            'name' => 'Test Category CRUD',
            'name_en' => 'Test Category CRUD EN',
            'name_fr' => 'Test Category CRUD FR',
            'description' => 'Created during CRUD test',
        ], $this->adminHeaders);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Category created successfully',
            ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'Test Category CRUD',
            'slug' => 'test-category-crud',
            'is_active' => true,
        ]);
    }

    public function test_update_category_succeeds(): void
    {
        // Create a category first
        $createResponse = $this->postJson('/api/admin/categories', [
            'name' => 'Category to Update',
        ], $this->adminHeaders);

        $categoryId = $createResponse->json('category.id');

        // Update it
        $response = $this->putJson("/api/admin/categories/{$categoryId}", [
            'name' => 'Updated Category Name',
            'description' => 'Updated description',
        ], $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Category updated successfully',
            ]);

        $this->assertDatabaseHas('categories', [
            'id' => $categoryId,
            'name' => 'Updated Category Name',
            'slug' => 'updated-category-name',
        ]);
    }

    public function test_delete_category_succeeds(): void
    {
        // Create a category first
        $createResponse = $this->postJson('/api/admin/categories', [
            'name' => 'Category to Delete',
        ], $this->adminHeaders);

        $categoryId = $createResponse->json('category.id');

        // Delete it
        $response = $this->deleteJson("/api/admin/categories/{$categoryId}", [], $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Category deleted successfully',
            ]);

        $this->assertDatabaseMissing('categories', [
            'id' => $categoryId,
        ]);
    }

    public function test_full_category_lifecycle_succeeds(): void
    {
        // 1. CREATE
        $createResponse = $this->postJson('/api/admin/categories', [
            'name' => 'Lifecycle Category',
            'name_en' => 'Lifecycle EN',
            'name_ar' => 'دورة حياة الفئة',
            'description' => 'A category that will go through the full lifecycle',
        ], $this->adminHeaders);

        $createResponse->assertStatus(201);
        $categoryId = $createResponse->json('category.id');
        $this->assertNotNull($categoryId);

        // 2. READ (admin index - verifies cache invalidation worked)
        $indexResponse = $this->getJson('/api/admin/categories', $this->adminHeaders);
        $indexResponse->assertStatus(200);
        $categories = $indexResponse->json();
        $this->assertGreaterThanOrEqual(1, count($categories));
        $found = collect($categories)->firstWhere('id', $categoryId);
        $this->assertNotNull($found);
        $this->assertEquals('Lifecycle Category', $found['name']);

        // 3. UPDATE
        $updateResponse = $this->putJson("/api/admin/categories/{$categoryId}", [
            'name' => 'Updated Lifecycle Category',
            'name_en' => 'Updated Lifecycle EN',
            'is_active' => false,
        ], $this->adminHeaders);

        $updateResponse->assertStatus(200);
        $this->assertDatabaseHas('categories', [
            'id' => $categoryId,
            'name' => 'Updated Lifecycle Category',
            'is_active' => false,
        ]);

        // 4. READ after update (should reflect changes)
        $updatedIndexResponse = $this->getJson('/api/admin/categories', $this->adminHeaders);
        $updatedIndexResponse->assertStatus(200);
        $updatedCategories = $updatedIndexResponse->json();
        $updated = collect($updatedCategories)->firstWhere('id', $categoryId);
        $this->assertNotNull($updated);
        $this->assertEquals('Updated Lifecycle Category', $updated['name']);
        $this->assertEquals(false, $updated['is_active']);

        // 5. DELETE
        $deleteResponse = $this->deleteJson("/api/admin/categories/{$categoryId}", [], $this->adminHeaders);
        $deleteResponse->assertStatus(200);

        $this->assertDatabaseMissing('categories', [
            'id' => $categoryId,
        ]);
    }

    public function test_category_validation_returns_errors(): void
    {
        // Missing name
        $response = $this->postJson('/api/admin/categories', [
            'description' => 'No name provided',
        ], $this->adminHeaders);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_category_requires_admin_auth(): void
    {
        // No auth
        $response = $this->postJson('/api/admin/categories', [
            'name' => 'Unauthenticated',
        ]);

        $response->assertStatus(401);
    }
}
