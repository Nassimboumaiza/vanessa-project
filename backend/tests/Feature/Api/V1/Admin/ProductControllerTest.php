<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for the Admin Product Controller.
 *
 * Covers all product management endpoints:
 * - Index (list with filters, search, pagination)
 * - Show (single product retrieval)
 * - Store (create product with validation)
 * - Update (modify product with validation)
 * - Destroy (delete product)
 * - Authorization and access control
 */
class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $baseAdminApiUrl = '/api/v1/admin';

    /**
     * Create and authenticate an admin user.
     */
    private function createAdminUser(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * Create and authenticate a regular customer user.
     */
    private function createCustomerUser(): User
    {
        return User::factory()->create(['role' => 'customer']);
    }

    /**
     * Get the admin API URL for a given path.
     */
    private function adminApiUrl(string $path): string
    {
        return "{$this->baseAdminApiUrl}/{$path}";
    }

    /**
     * Get valid product data for testing.
     *
     * @return array<string, mixed>
     */
    private function getValidProductData(Category $category): array
    {
        return [
            'name' => 'Premium Perfume',
            'description' => 'A luxurious fragrance with notes of jasmine and sandalwood.',
            'short_description' => 'Luxury jasmine perfume',
            'category_id' => $category->id,
            'price' => 199.99,
            'compare_price' => 249.99,
            'cost_price' => 120.00,
            'stock_quantity' => 50,
            'sku' => 'PRD-'.uniqid(),
            'barcode' => '1234567890123',
            'weight' => 0.5,
            'concentration' => 'Eau de Parfum',
            'volume_ml' => 100,
            'brand' => 'Luxury Scents',
            'gender' => 'unisex',
            'is_active' => true,
            'is_featured' => false,
        ];
    }

    // ==========================================
    // INDEX TESTS
    // ==========================================

    /**
     * @test
     * Admin can retrieve paginated product list.
     */
    public function admin_can_retrieve_paginated_product_list(): void
    {
        // Arrange
        $admin = $this->createAdminUser();
        Product::factory()->count(20)->create();

        // Act
        $response = $this->actingAsUser($admin)->getJson($this->adminApiUrl('products'));

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'price',
                        'category',
                    ],
                ],
                'pagination' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    /**
     * @test
     * Product list can be filtered by category.
     */
    public function product_list_can_be_filtered_by_category(): void
    {
        // Arrange
        $admin = $this->createAdminUser();
        $category = Category::factory()->create();
        Product::factory()->count(3)->create(['category_id' => $category->id]);
        Product::factory()->count(2)->create();

        // Act
        $response = $this->actingAsUser($admin)->getJson($this->adminApiUrl('products?category='.$category->id));

        // Assert
        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    /**
     * @test
     * Product list can be filtered by status.
     */
    public function product_list_can_be_filtered_by_status(): void
    {
        // Arrange
        $admin = $this->createAdminUser();
        Product::factory()->count(3)->create(['is_active' => true]);
        Product::factory()->count(2)->create(['is_active' => false]);

        // Act
        $response = $this->actingAsUser($admin)->getJson($this->adminApiUrl('products?status=active'));

        // Assert
        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    /**
     * @test
     * Product list can be searched by name.
     */
    public function product_list_can_be_searched_by_name(): void
    {
        // Arrange
        $admin = $this->createAdminUser();
        Product::factory()->create(['name' => 'Special Perfume']);
        Product::factory()->count(3)->create();

        // Act
        $response = $this->actingAsUser($admin)->getJson($this->adminApiUrl('products?search=Special'));

        // Assert
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Special Perfume', $response->json('data.0.name'));
    }

    /**
     * @test
     * Product list can be searched by SKU.
     */
    public function product_list_can_be_searched_by_sku(): void
    {
        // Arrange
        $admin = $this->createAdminUser();
        Product::factory()->create(['sku' => 'UNIQUE-SKU-123']);
        Product::factory()->count(3)->create();

        // Act
        $response = $this->actingAsUser($admin)->getJson($this->adminApiUrl('products?search=UNIQUE-SKU'));

        // Assert
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    /**
     * @test
     * Product list supports custom per page pagination.
     */
    public function product_list_supports_custom_per_page_pagination(): void
    {
        // Arrange
        $admin = $this->createAdminUser();
        Product::factory()->count(25)->create();

        // Act
        $response = $this->actingAsUser($admin)->getJson($this->adminApiUrl('products?per_page=10'));

        // Assert
        $response->assertStatus(200);
        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(25, $response->json('pagination.total'));
    }

    // ==========================================
    // SHOW TESTS
    // ==========================================

    /**
     * @test
     * Admin can retrieve single product details.
     */
    public function admin_can_retrieve_single_product_details(): void
    {
        // Arrange
        $admin = $this->createAdminUser();
        $product = Product::factory()
            ->withImages(2)
            ->withVariants(2)
            ->create();

        // Act
        $response = $this->actingAsUser($admin)->getJson($this->adminApiUrl("products/{$product->id}"));

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'description',
                    'price',
                    'category',
                    'images',
                    'variants',
                    'reviews',
                ],
            ])
            ->assertJson([
                'data' => [
                    'id' => $product->id,
                    'name' => $product->name,
                ],
            ]);
    }

    /**
     * @test
     * Retrieving non-existent product returns 404.
     */
    public function retrieving_non_existent_product_returns_404(): void
    {
        // Arrange
        $admin = $this->createAdminUser();

        // Act
        $response = $this->actingAsUser($admin)->getJson($this->adminApiUrl('products/99999'));

        // Assert
        $response->assertStatus(404);
    }

    // ==========================================
    // STORE TESTS
    // ==========================================

    /**
     * @test
     * Admin can create a new product successfully.
     */
    public function admin_can_create_new_product_successfully(): void
    {
        // Arrange
        $admin = $this->createAdminUser();
        $category = Category::factory()->create();
        $productData = $this->getValidProductData($category);

        // Act
        $response = $this->actingAsUser($admin)->postJson($this->adminApiUrl('products'), $productData);

        // Assert
        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'category',
                ],
            ]);

        $this->assertDatabaseHas('products', [
            'name' => $productData['name'],
            'sku' => $productData['sku'],
            'category_id' => $category->id,
        ]);
    }

    /**
     * @test
     * Creating product auto-generates slug from name.
     */
    public function creating_product_auto_generates_slug_from_name(): void
    {
        // Arrange
        $admin = $this->createAdminUser();
        $category = Category::factory()->create();
        $productData = $this->getValidProductData($category);

        // Act
        $response = $this->actingAsUser($admin)->postJson($this->adminApiUrl('products'), $productData);

        // Assert
        $response->assertStatus(201);
        $this->assertDatabaseHas('products', [
            'name' => $productData['name'],
            'slug' => 'premium-perfume',
        ]);
    }

    /**
     * @test
     * Creating product requires all mandatory fields.
     */
    public function creating_product_requires_mandatory_fields(): void
    {
        // Arrange
        $admin = $this->createAdminUser();

        // Act
        $response = $this->actingAsUser($admin)->postJson($this->adminApiUrl('products'), []);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'category_id', 'price', 'sku']);
    }

    /**
     * @test
     * Creating product requires valid category_id.
     */
    public function creating_product_requires_valid_category_id(): void
    {
        // Arrange
        $admin = $this->createAdminUser();
        $category = Category::factory()->create();
        $productData = $this->getValidProductData($category);
        $productData['category_id'] = 99999;

        // Act
        $response = $this->actingAsUser($admin)->postJson($this->adminApiUrl('products'), $productData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);
    }

    /**
     * @test
     * Creating product requires unique SKU.
     */
    public function creating_product_requires_unique_sku(): void
    {
        // Arrange
        $admin = $this->createAdminUser();
        $existingProduct = Product::factory()->create();
        $category = Category::factory()->create();
        $productData = $this->getValidProductData($category);
        $productData['sku'] = $existingProduct->sku;

        // Act
        $response = $this->actingAsUser($admin)->postJson($this->adminApiUrl('products'), $productData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sku']);
    }

    /**
     * @test
     * Creating product with variants stores variants correctly.
     */
    public function creating_product_with_variants_stores_variants_correctly(): void
    {
        // Arrange
        $admin = $this->createAdminUser();
        $category = Category::factory()->create();
        $productData = $this->getValidProductData($category);
        $productData['variants'] = [
            [
                'name' => '50ml',
                'sku' => 'VAR-'.uniqid(),
                'price' => 99.99,
                'stock_quantity' => 30,
                'volume_ml' => 50,
            ],
            [
                'name' => '100ml',
                'sku' => 'VAR-'.uniqid(),
                'price' => 149.99,
                'stock_quantity' => 20,
                'volume_ml' => 100,
            ],
        ];

        // Act
        $response = $this->actingAsUser($admin)->postJson($this->adminApiUrl('products'), $productData);

        // Assert
        $response->assertStatus(201);
        $productId = $response->json('data.id');
        $this->assertDatabaseCount('product_variants', 2);
        $this->assertDatabaseHas('product_variants', [
            'product_id' => $productId,
            'name' => '50ml',
        ]);
    }

    // ==========================================
    // UPDATE TESTS
    // ==========================================

    /**
     * @test
     * Admin can update an existing product.
     */
    public function admin_can_update_existing_product(): void
    {
        // Arrange
        $admin = $this->createAdminUser();
        $product = Product::factory()->create();
        $updateData = [
            'name' => 'Updated Perfume Name',
            'price' => 299.99,
        ];

        // Act
        $response = $this->actingAsUser($admin)->putJson($this->adminApiUrl("products/{$product->id}"), $updateData);

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Updated Perfume Name',
                    'price' => 299.99,
                ],
            ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Perfume Name',
        ]);
    }

    /**
     * @test
     * Updating product with name change regenerates slug.
     */
    public function updating_product_with_name_change_regenerates_slug(): void
    {
        // Arrange
        $admin = $this->createAdminUser();
        $product = Product::factory()->create(['name' => 'Original Name', 'slug' => 'original-name']);

        // Act
        $response = $this->actingAsUser($admin)->putJson(
            $this->adminApiUrl("products/{$product->id}"),
            ['name' => 'Updated Name']
        );

        // Assert
        $response->assertStatus(200);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Name',
            'slug' => 'updated-name',
        ]);
    }

    /**
     * @test
     * Updating non-existent product returns 404.
     */
    public function updating_non_existent_product_returns_404(): void
    {
        // Arrange
        $admin = $this->createAdminUser();

        // Act
        $response = $this->actingAsUser($admin)->putJson($this->adminApiUrl('products/99999'), ['name' => 'Test']);

        // Assert
        $response->assertStatus(404);
    }

    // ==========================================
    // DESTROY TESTS
    // ==========================================

    /**
     * @test
     * Admin can delete a product.
     */
    public function admin_can_delete_product(): void
    {
        // Arrange
        $admin = $this->createAdminUser();
        $product = Product::factory()->create();

        // Act
        $response = $this->actingAsUser($admin)->deleteJson($this->adminApiUrl("products/{$product->id}"));

        // Assert
        $response->assertStatus(204);
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    /**
     * @test
     * Deleting non-existent product returns 404.
     */
    public function deleting_non_existent_product_returns_404(): void
    {
        // Arrange
        $admin = $this->createAdminUser();

        // Act
        $response = $this->actingAsUser($admin)->deleteJson($this->adminApiUrl('products/99999'));

        // Assert
        $response->assertStatus(404);
    }

    // ==========================================
    // AUTHORIZATION TESTS
    // ==========================================

    /**
     * @test
     * Unauthenticated user cannot access admin product endpoints.
     */
    public function unauthenticated_user_cannot_access_admin_product_endpoints(): void
    {
        // Arrange
        $product = Product::factory()->create();

        // Act & Assert - Index
        $this->getJson($this->adminApiUrl('products'))->assertStatus(401);

        // Act & Assert - Show
        $this->getJson($this->adminApiUrl("products/{$product->id}"))->assertStatus(401);

        // Act & Assert - Store
        $this->postJson($this->adminApiUrl('products'), [])->assertStatus(401);

        // Act & Assert - Update
        $this->putJson($this->adminApiUrl("products/{$product->id}"), [])->assertStatus(401);

        // Act & Assert - Destroy
        $this->deleteJson($this->adminApiUrl("products/{$product->id}"))->assertStatus(401);
    }

    /**
     * @test
     * Non-admin user cannot access admin product endpoints.
     */
    public function non_admin_user_cannot_access_admin_product_endpoints(): void
    {
        // Arrange
        $customer = $this->createCustomerUser();
        $product = Product::factory()->create();

        // Act & Assert - Index
        $this->actingAsUser($customer)->getJson($this->adminApiUrl('products'))->assertStatus(403);

        // Act & Assert - Show
        $this->actingAsUser($customer)->getJson($this->adminApiUrl("products/{$product->id}"))->assertStatus(403);

        // Act & Assert - Store
        $this->actingAsUser($customer)->postJson($this->adminApiUrl('products'), [])->assertStatus(403);

        // Act & Assert - Update
        $this->actingAsUser($customer)->putJson($this->adminApiUrl("products/{$product->id}"), [])->assertStatus(403);

        // Act & Assert - Destroy
        $this->actingAsUser($customer)->deleteJson($this->adminApiUrl("products/{$product->id}"))->assertStatus(403);
    }

    /**
     * @test
     * Admin can access all product endpoints.
     */
    public function admin_can_access_all_product_endpoints(): void
    {
        // Arrange
        $admin = $this->createAdminUser();
        $product = Product::factory()->create();
        $category = Category::factory()->create();

        // Act & Assert - Index
        $this->actingAsUser($admin)->getJson($this->adminApiUrl('products'))->assertStatus(200);

        // Act & Assert - Show
        $this->actingAsUser($admin)->getJson($this->adminApiUrl("products/{$product->id}"))->assertStatus(200);

        // Act & Assert - Store
        $productData = $this->getValidProductData($category);
        $this->actingAsUser($admin)->postJson($this->adminApiUrl('products'), $productData)->assertStatus(201);

        // Act & Assert - Update
        $this->actingAsUser($admin)->putJson($this->adminApiUrl("products/{$product->id}"), ['name' => 'Updated'])->assertStatus(200);

        // Act & Assert - Destroy
        $newProduct = Product::factory()->create();
        $this->actingAsUser($admin)->deleteJson($this->adminApiUrl("products/{$newProduct->id}"))->assertStatus(204);
    }
}
