<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Integration tests for the complete checkout workflow.
 *
 * Validates end-to-end checkout process including:
 * - Authentication
 * - Cart validation
 * - Order creation
 * - Stock management
 * - Transactional integrity
 * - API response structure
 */
class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Get valid order payload for checkout.
     *
     * @return array<string, mixed>
     */
    private function getValidOrderPayload(): array
    {
        return [
            'payment_method' => 'credit_card',
            'shipping_address' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'company' => 'Acme Inc',
                'address_line_1' => '123 Main Street',
                'address_line_2' => 'Apt 4B',
                'city' => 'New York',
                'state' => 'NY',
                'postal_code' => '10001',
                'country' => 'USA',
                'phone' => '+1234567890',
            ],
            'billing_address' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'company' => 'Acme Inc',
                'address_line_1' => '123 Main Street',
                'address_line_2' => 'Apt 4B',
                'city' => 'New York',
                'state' => 'NY',
                'postal_code' => '10001',
                'country' => 'USA',
                'phone' => '+1234567890',
            ],
            'customer_notes' => 'Please leave package at the front door',
        ];
    }

    /**
     * Setup a cart with products for a user.
     */
    private function setupCartWithProducts(
        User $user,
        int $productCount = 2,
        ?int $quantityPerProduct = null,
        ?int $stockQuantity = null,
        bool $withVariant = false
    ): Cart {
        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'total_amount' => 0,
            'total_items' => 0,
        ]);

        $totalAmount = 0;
        $totalItems = 0;

        for ($i = 0; $i < $productCount; $i++) {
            $product = Product::factory()->create([
                'stock_quantity' => $stockQuantity ?? 100,
                'is_active' => true,
                'price' => 99.99 + ($i * 50), // Varying prices
            ]);

            $quantity = $quantityPerProduct ?? ($i + 1);
            $unitPrice = $product->price;
            $totalPrice = $quantity * $unitPrice;

            $cartItemData = [
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $totalPrice,
            ];

            if ($withVariant) {
                $variant = ProductVariant::factory()->create([
                    'product_id' => $product->id,
                    'stock_quantity' => $stockQuantity ?? 100,
                    'is_active' => true,
                    'price' => $unitPrice + 20,
                ]);
                $cartItemData['variant_id'] = $variant->id;
            }

            CartItem::factory()->create($cartItemData);

            $totalAmount += $totalPrice;
            $totalItems += $quantity;
        }

        $cart->update([
            'total_amount' => $totalAmount,
            'total_items' => $totalItems,
        ]);

        return $cart->fresh();
    }

    // ==========================================
    // SUCCESSFUL CHECKOUT FLOW TESTS
    // ==========================================

    /**
     * @test
     * Complete checkout flow succeeds with valid data.
     */
    public function complete_checkout_flow_succeeds_with_valid_data(): void
    {
        // Arrange
        $user = User::factory()->create();
        $cart = $this->setupCartWithProducts($user, 2);
        $initialStock = Product::first()->stock_quantity;
        $orderPayload = $this->getValidOrderPayload();

        // Act
        $response = $this->actingAsUser($user)
            ->postJson('/api/v1/orders', $orderPayload);

        // Assert - HTTP Response
        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'order_number',
                    'status',
                    'payment_status',
                    'payment_method',
                    'currency',
                    'subtotal',
                    'discount_amount',
                    'shipping_amount',
                    'tax_amount',
                    'total_amount',
                    'items',
                    'shipping_address',
                    'billing_address',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => [
                    'status' => 'pending',
                    'payment_status' => 'pending',
                    'payment_method' => 'credit_card',
                    'currency' => 'USD',
                ],
            ]);

        // Assert - Order exists in database
        $orderId = $response->json('data.id');
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'user_id' => $user->id,
            'status' => 'pending',
            'payment_method' => 'credit_card',
        ]);

        // Assert - Order items created
        $this->assertDatabaseCount('order_items', 2);

        // Assert - Stock reduced
        $product = Product::first();
        $this->assertEquals($initialStock - 1, $product->fresh()->stock_quantity);

        // Assert - Cart cleared
        $cart->refresh();
        $this->assertEquals(0, $cart->total_items);
        $this->assertEquals(0, $cart->total_amount);
        $this->assertDatabaseMissing('cart_items', ['cart_id' => $cart->id]);

        // Assert - Status history created
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $orderId,
            'status' => 'pending',
            'notes' => 'Order created',
        ]);
    }

    /**
     * @test
     * Checkout with variants deducts variant stock.
     */
    public function checkout_with_variants_deducts_variant_stock(): void
    {
        // Arrange
        $user = User::factory()->create();
        $cart = $this->setupCartWithProducts($user, 1, 3, 50, true); // 1 product, qty 3, stock 50, with variant
        $product = Product::first();
        $variant = ProductVariant::where('product_id', $product->id)->first();
        $initialVariantStock = $variant->stock_quantity;

        $orderPayload = $this->getValidOrderPayload();

        // Act
        $response = $this->actingAsUser($user)
            ->postJson('/api/v1/orders', $orderPayload);

        // Assert
        $response->assertStatus(201);

        // Variant stock reduced, product stock unchanged
        $this->assertEquals($initialVariantStock - 3, $variant->fresh()->stock_quantity);
        $this->assertEquals(50, $product->fresh()->stock_quantity); // Unchanged

        // Order item has variant info
        $orderId = $response->json('data.id');
        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'variant_id' => $variant->id,
        ]);
    }

    /**
     * @test
     * Order totals are calculated correctly with free shipping.
     */
    public function order_totals_calculated_correctly_with_free_shipping(): void
    {
        // Arrange - Cart over $100 for free shipping
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'price' => 150.00,
            'stock_quantity' => 100,
            'is_active' => true,
        ]);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 150.00,
            'total_price' => 150.00,
        ]);
        $cart->update(['total_amount' => 150.00, 'total_items' => 1]);

        $orderPayload = $this->getValidOrderPayload();

        // Act
        $response = $this->actingAsUser($user)
            ->postJson('/api/v1/orders', $orderPayload);

        // Assert
        $response->assertStatus(201);
        $data = $response->json('data');

        // Subtotal: 150.00, Shipping: 0 (free over $100), Tax: 15.00 (10%), Total: 165.00
        $this->assertEquals(150.00, $data['subtotal']);
        $this->assertEquals(0, $data['shipping_amount']);
        $this->assertEqualsWithDelta(15.00, $data['tax_amount'], 0.01);
        $this->assertEqualsWithDelta(165.00, $data['total_amount'], 0.01);
    }

    /**
     * @test
     * Order totals include shipping under $100 threshold.
     */
    public function order_totals_include_shipping_under_threshold(): void
    {
        // Arrange - Cart under $100 for paid shipping
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'price' => 50.00,
            'stock_quantity' => 100,
            'is_active' => true,
        ]);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 50.00,
            'total_price' => 50.00,
        ]);
        $cart->update(['total_amount' => 50.00, 'total_items' => 1]);

        $orderPayload = $this->getValidOrderPayload();

        // Act
        $response = $this->actingAsUser($user)
            ->postJson('/api/v1/orders', $orderPayload);

        // Assert
        $response->assertStatus(201);
        $data = $response->json('data');

        // Subtotal: 50.00, Shipping: 15.00, Tax: 5.00 (10%), Total: 70.00
        $this->assertEquals(50.00, $data['subtotal']);
        $this->assertEquals(15.00, $data['shipping_amount']);
        $this->assertEqualsWithDelta(5.00, $data['tax_amount'], 0.01);
        $this->assertEqualsWithDelta(70.00, $data['total_amount'], 0.01);
    }

    /**
     * @test
     * Order number follows expected format.
     */
    public function order_number_follows_expected_format(): void
    {
        // Arrange
        $user = User::factory()->create();
        $cart = $this->setupCartWithProducts($user, 1);
        $orderPayload = $this->getValidOrderPayload();

        // Act
        $response = $this->actingAsUser($user)
            ->postJson('/api/v1/orders', $orderPayload);

        // Assert
        $response->assertStatus(201);
        $orderNumber = $response->json('data.order_number');

        // Format: VP-YYYYMMDD-XXXX
        $this->assertMatchesRegularExpression('/^VP-\d{8}-[A-Z0-9]{4}$/', $orderNumber);
    }

    // ==========================================
    // FAILURE & EDGE CASE SCENARIOS
    // ==========================================

    /**
     * @test
     * Unauthenticated user cannot complete checkout.
     */
    public function unauthenticated_user_cannot_complete_checkout(): void
    {
        // Arrange
        $orderPayload = $this->getValidOrderPayload();

        // Act
        $response = $this->postJson('/api/v1/orders', $orderPayload);

        // Assert
        $response->assertStatus(401);
        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * @test
     * Checkout fails with empty cart.
     */
    public function checkout_fails_with_empty_cart(): void
    {
        // Arrange
        $user = User::factory()->create();
        Cart::factory()->create([
            'user_id' => $user->id,
            'total_items' => 0,
            'total_amount' => 0,
        ]);
        $orderPayload = $this->getValidOrderPayload();

        // Act
        $response = $this->actingAsUser($user)
            ->postJson('/api/v1/orders', $orderPayload);

        // Assert
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Cart is empty',
            ]);
        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * @test
     * Checkout fails when product is inactive.
     */
    public function checkout_fails_when_product_is_inactive(): void
    {
        // Arrange
        $user = User::factory()->create();
        $inactiveProduct = Product::factory()->create([
            'is_active' => false,
            'stock_quantity' => 100,
        ]);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $inactiveProduct->id,
            'quantity' => 1,
            'unit_price' => 99.99,
            'total_price' => 99.99,
        ]);
        $cart->update(['total_amount' => 99.99, 'total_items' => 1]);

        $orderPayload = $this->getValidOrderPayload();

        // Act
        $response = $this->actingAsUser($user)
            ->postJson('/api/v1/orders', $orderPayload);

        // Assert
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => "Product '{$inactiveProduct->name}' is no longer available",
            ]);
        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * @test
     * Checkout fails when stock is insufficient.
     */
    public function checkout_fails_when_stock_is_insufficient(): void
    {
        // Arrange
        $user = User::factory()->create();
        $lowStockProduct = Product::factory()->create([
            'stock_quantity' => 2,
            'is_active' => true,
        ]);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $lowStockProduct->id,
            'quantity' => 5, // More than available stock
            'unit_price' => 99.99,
            'total_price' => 499.95,
        ]);
        $cart->update(['total_amount' => 499.95, 'total_items' => 5]);

        $orderPayload = $this->getValidOrderPayload();

        // Act
        $response = $this->actingAsUser($user)
            ->postJson('/api/v1/orders', $orderPayload);

        // Assert
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => "Insufficient stock for '{$lowStockProduct->name}'",
            ]);
        $this->assertDatabaseCount('orders', 0);

        // Stock should remain unchanged
        $this->assertEquals(2, $lowStockProduct->fresh()->stock_quantity);
    }

    /**
     * @test
     * Checkout fails with invalid payment method.
     */
    public function checkout_fails_with_invalid_payment_method(): void
    {
        // Arrange
        $user = User::factory()->create();
        $cart = $this->setupCartWithProducts($user, 1);
        $orderPayload = $this->getValidOrderPayload();
        $orderPayload['payment_method'] = 'bitcoin';

        // Act
        $response = $this->actingAsUser($user)
            ->postJson('/api/v1/orders', $orderPayload);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);
        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * @test
     * Checkout fails with missing required address fields.
     */
    public function checkout_fails_with_missing_required_address_fields(): void
    {
        // Arrange
        $user = User::factory()->create();
        $cart = $this->setupCartWithProducts($user, 1);
        $orderPayload = $this->getValidOrderPayload();
        unset($orderPayload['shipping_address']['first_name']);
        unset($orderPayload['billing_address']['city']);

        // Act
        $response = $this->actingAsUser($user)
            ->postJson('/api/v1/orders', $orderPayload);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'shipping_address.first_name',
                'billing_address.city',
            ]);
        $this->assertDatabaseCount('orders', 0);
    }

    /**
     * @test
     * Checkout fails when user has no cart.
     */
    public function checkout_fails_when_user_has_no_cart(): void
    {
        // Arrange
        $user = User::factory()->create();
        // No cart created
        $orderPayload = $this->getValidOrderPayload();

        // Act
        $response = $this->actingAsUser($user)
            ->postJson('/api/v1/orders', $orderPayload);

        // Assert
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Cart is empty',
            ]);
        $this->assertDatabaseCount('orders', 0);
    }

    // ==========================================
    // TRANSACTIONAL INTEGRITY TESTS
    // ==========================================

    /**
     * @test
     * Stock is not deducted when checkout fails validation.
     */
    public function stock_not_deducted_when_checkout_fails_validation(): void
    {
        // Arrange
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        $cart = Cart::factory()->create(['user_id' => $user->id]);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 99.99,
            'total_price' => 499.95,
        ]);
        $cart->update(['total_amount' => 499.95, 'total_items' => 5]);

        $initialStock = $product->stock_quantity;

        // Invalid payload - missing required field
        $orderPayload = $this->getValidOrderPayload();
        unset($orderPayload['payment_method']);

        // Act
        $response = $this->actingAsUser($user)
            ->postJson('/api/v1/orders', $orderPayload);

        // Assert
        $response->assertStatus(422);
        $this->assertEquals($initialStock, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    /**
     * @test
     * Partial checkout with multiple items rolls back on single failure.
     */
    public function partial_checkout_rolls_back_on_failure(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Product 1 - sufficient stock
        $product1 = Product::factory()->create([
            'stock_quantity' => 100,
            'is_active' => true,
        ]);

        // Product 2 - insufficient stock (will fail)
        $product2 = Product::factory()->create([
            'stock_quantity' => 1,
            'is_active' => true,
        ]);

        $cart = Cart::factory()->create(['user_id' => $user->id]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product1->id,
            'quantity' => 10,
            'unit_price' => 50.00,
            'total_price' => 500.00,
        ]);

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product2->id,
            'quantity' => 5, // More than available
            'unit_price' => 50.00,
            'total_price' => 250.00,
        ]);

        $cart->update(['total_amount' => 750.00, 'total_items' => 15]);

        $initialStock1 = $product1->stock_quantity;
        $initialStock2 = $product2->stock_quantity;

        $orderPayload = $this->getValidOrderPayload();

        // Act
        $response = $this->actingAsUser($user)
            ->postJson('/api/v1/orders', $orderPayload);

        // Assert
        $response->assertStatus(400);

        // No order should be created
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);

        // Stock should remain unchanged for both products
        $this->assertEquals($initialStock1, $product1->fresh()->stock_quantity);
        $this->assertEquals($initialStock2, $product2->fresh()->stock_quantity);

        // Cart should not be cleared
        $cart->refresh();
        $this->assertEquals(15, $cart->total_items);
    }

    // ==========================================
    // API RESOURCE RESPONSE TESTS
    // ==========================================

    /**
     * @test
     * Checkout response matches OrderResource structure.
     */
    public function checkout_response_matches_order_resource_structure(): void
    {
        // Arrange
        $user = User::factory()->create();
        $cart = $this->setupCartWithProducts($user, 2);
        $orderPayload = $this->getValidOrderPayload();

        // Act
        $response = $this->actingAsUser($user)
            ->postJson('/api/v1/orders', $orderPayload);

        // Assert
        $response->assertStatus(201);

        // Verify all expected fields in response
        $data = $response->json('data');
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('order_number', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('payment_status', $data);
        $this->assertArrayHasKey('payment_method', $data);
        $this->assertArrayHasKey('currency', $data);
        $this->assertArrayHasKey('subtotal', $data);
        $this->assertArrayHasKey('discount_amount', $data);
        $this->assertArrayHasKey('shipping_amount', $data);
        $this->assertArrayHasKey('tax_amount', $data);
        $this->assertArrayHasKey('total_amount', $data);
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('shipping_address', $data);
        $this->assertArrayHasKey('billing_address', $data);
        $this->assertArrayHasKey('created_at', $data);

        // Verify items structure
        $this->assertCount(2, $data['items']);
        $item = $data['items'][0];
        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('product_name', $item);
        $this->assertArrayHasKey('product_sku', $item);
        $this->assertArrayHasKey('quantity', $item);
        $this->assertArrayHasKey('unit_price', $item);
        $this->assertArrayHasKey('total_price', $item);

        // Verify address structure
        $this->assertArrayHasKey('first_name', $data['shipping_address']);
        $this->assertArrayHasKey('last_name', $data['shipping_address']);
        $this->assertArrayHasKey('address_line_1', $data['shipping_address']);
        $this->assertArrayHasKey('city', $data['shipping_address']);
    }

    /**
     * @test
     * Created order can be retrieved via show endpoint.
     */
    public function created_order_can_be_retrieved_via_show_endpoint(): void
    {
        // Arrange
        $user = User::factory()->create();
        $cart = $this->setupCartWithProducts($user, 2);
        $orderPayload = $this->getValidOrderPayload();

        // Create order
        $createResponse = $this->actingAsUser($user)
            ->postJson('/api/v1/orders', $orderPayload);

        $orderId = $createResponse->json('data.id');

        // Act - Retrieve the order
        $showResponse = $this->actingAsUser($user)
            ->getJson("/api/v1/orders/{$orderId}");

        // Assert
        $showResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $orderId,
                    'status' => 'pending',
                ],
            ]);
    }

    /**
     * @test
     * Created order appears in order list.
     */
    public function created_order_appears_in_order_list(): void
    {
        // Arrange
        $user = User::factory()->create();
        $cart = $this->setupCartWithProducts($user, 1);
        $orderPayload = $this->getValidOrderPayload();

        // Create order
        $createResponse = $this->actingAsUser($user)
            ->postJson('/api/v1/orders', $orderPayload);

        $orderId = $createResponse->json('data.id');

        // Act - Get order list
        $listResponse = $this->actingAsUser($user)
            ->getJson('/api/v1/orders');

        // Assert
        $listResponse->assertStatus(200);
        $orders = $listResponse->json('data');

        $foundOrder = false;
        foreach ($orders as $order) {
            if ($order['id'] === $orderId) {
                $foundOrder = true;
                break;
            }
        }

        $this->assertTrue($foundOrder, 'Created order should appear in order list');
    }
}
