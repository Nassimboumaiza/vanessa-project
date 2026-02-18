<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for the Order Controller.
 *
 * Covers all order-related endpoints:
 * - Index (list user's orders with pagination)
 * - Show (single order retrieval)
 * - Store (create order with validation)
 * - Tracking (order tracking information)
 * - Authorization and access control
 */
class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Get valid order data for testing.
     *
     * @return array<string, mixed>
     */
    private function getValidOrderData(): array
    {
        return [
            'payment_method' => 'credit_card',
            'shipping_address' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'address_line_1' => '123 Main St',
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
                'address_line_1' => '123 Main St',
                'address_line_2' => 'Apt 4B',
                'city' => 'New York',
                'state' => 'NY',
                'postal_code' => '10001',
                'country' => 'USA',
                'phone' => '+1234567890',
            ],
            'customer_notes' => 'Please gift wrap this order.',
        ];
    }

    /**
     * Setup cart with items for a user.
     */
    private function setupCartWithItems(User $user, int $itemCount = 2): Cart
    {
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        
        $totalAmount = 0;
        $totalItems = 0;
        
        for ($i = 0; $i < $itemCount; $i++) {
            $product = Product::factory()->create([
                'stock_quantity' => 100,
                'is_active' => true,
            ]);
            
            $quantity = 2;
            $unitPrice = $product->price;
            $totalPrice = $quantity * $unitPrice;
            
            CartItem::factory()->create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $totalPrice,
            ]);
            
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
    // INDEX TESTS
    // ==========================================

    /**
     * @test
     * Authenticated user can retrieve their orders.
     */
    public function authenticated_user_can_retrieve_their_orders(): void
    {
        // Arrange
        $user = User::factory()->create();
        Order::factory()->count(5)->forUser($user)->create();
        Order::factory()->count(3)->create(); // Other users' orders

        // Act
        $response = $this->actingAsUser($user)->getJson($this->apiUrl('orders'));

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'pagination',
            ]);
        
        // Should only see their own 5 orders
        $this->assertCount(5, $response->json('data'));
    }

    /**
     * @test
     * Order list is paginated.
     */
    public function order_list_is_paginated(): void
    {
        // Arrange
        $user = User::factory()->create();
        Order::factory()->count(25)->forUser($user)->create();

        // Act
        $response = $this->actingAsUser($user)->getJson($this->apiUrl('orders?per_page=10'));

        // Assert
        $response->assertStatus(200);
        $this->assertCount(10, $response->json('data'));
        $this->assertEquals(25, $response->json('pagination.total'));
    }

    /**
     * @test
     * Unauthenticated user cannot access orders.
     */
    public function unauthenticated_user_cannot_access_orders(): void
    {
        // Act
        $response = $this->getJson($this->apiUrl('orders'));

        // Assert
        $response->assertStatus(401);
    }

    // ==========================================
    // SHOW TESTS
    // ==========================================

    /**
     * @test
     * User can retrieve their own order details.
     */
    public function user_can_retrieve_their_own_order_details(): void
    {
        // Arrange
        $user = User::factory()->create();
        $order = Order::factory()->forUser($user)->withItems(2)->create();

        // Act
        $response = $this->actingAsUser($user)->getJson($this->apiUrl("orders/{$order->id}"));

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'order_number',
                    'status',
                    'items',
                ],
            ])
            ->assertJson([
                'data' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                ],
            ]);
    }

    /**
     * @test
     * User cannot retrieve another user's order.
     */
    public function user_cannot_retrieve_another_users_order(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $order = Order::factory()->forUser($otherUser)->create();

        // Act
        $response = $this->actingAsUser($user)->getJson($this->apiUrl("orders/{$order->id}"));

        // Assert
        $response->assertStatus(404);
    }

    /**
     * @test
     * Retrieving non-existent order returns 404.
     */
    public function retrieving_non_existent_order_returns_404(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAsUser($user)->getJson($this->apiUrl('orders/99999'));

        // Assert
        $response->assertStatus(404);
    }

    // ==========================================
    // STORE TESTS
    // ==========================================

    /**
     * @test
     * User can create order with valid cart.
     */
    public function user_can_create_order_with_valid_cart(): void
    {
        // Arrange
        $user = User::factory()->create();
        $this->setupCartWithItems($user, 2);
        $orderData = $this->getValidOrderData();

        // Act
        $response = $this->actingAsUser($user)->postJson($this->apiUrl('orders'), $orderData);

        // Assert
        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'order_number',
                    'status',
                    'total_amount',
                ],
            ]);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        // Cart should be cleared
        $this->assertDatabaseMissing('cart_items', ['cart_id' => $user->cart?->id]);
    }

    /**
     * @test
     * Creating order requires all mandatory fields.
     */
    public function creating_order_requires_mandatory_fields(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAsUser($user)->postJson($this->apiUrl('orders'), []);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'payment_method',
                'shipping_address',
                'billing_address',
            ]);
    }

    /**
     * @test
     * Creating order fails with empty cart.
     */
    public function creating_order_fails_with_empty_cart(): void
    {
        // Arrange
        $user = User::factory()->create();
        Cart::factory()->create(['user_id' => $user->id, 'total_items' => 0]);
        $orderData = $this->getValidOrderData();

        // Act
        $response = $this->actingAsUser($user)->postJson($this->apiUrl('orders'), $orderData);

        // Assert
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Cart is empty',
            ]);
    }

    /**
     * @test
     * Creating order fails when product is inactive.
     */
    public function creating_order_fails_when_product_is_inactive(): void
    {
        // Arrange
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $inactiveProduct = Product::factory()->create(['is_active' => false]);
        
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $inactiveProduct->id,
        ]);
        
        $orderData = $this->getValidOrderData();

        // Act
        $response = $this->actingAsUser($user)->postJson($this->apiUrl('orders'), $orderData);

        // Assert
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => "Product '{$inactiveProduct->name}' is no longer available",
            ]);
    }

    /**
     * @test
     * Creating order fails when stock is insufficient.
     */
    public function creating_order_fails_when_stock_is_insufficient(): void
    {
        // Arrange
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $lowStockProduct = Product::factory()->create([
            'stock_quantity' => 1,
            'is_active' => true,
        ]);
        
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $lowStockProduct->id,
            'quantity' => 5,
        ]);
        
        $orderData = $this->getValidOrderData();

        // Act
        $response = $this->actingAsUser($user)->postJson($this->apiUrl('orders'), $orderData);

        // Assert
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => "Insufficient stock for '{$lowStockProduct->name}'",
            ]);
    }

    /**
     * @test
     * Creating order reduces product stock.
     */
    public function creating_order_reduces_product_stock(): void
    {
        // Arrange
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $product = Product::factory()->create([
            'stock_quantity' => 100,
            'is_active' => true,
        ]);
        
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => $product->price,
            'total_price' => 5 * $product->price,
        ]);
        
        $orderData = $this->getValidOrderData();

        // Act
        $response = $this->actingAsUser($user)->postJson($this->apiUrl('orders'), $orderData);

        // Assert
        $response->assertStatus(201);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 95, // 100 - 5
        ]);
    }

    /**
     * @test
     * Creating order creates order items.
     */
    public function creating_order_creates_order_items(): void
    {
        // Arrange
        $user = User::factory()->create();
        $this->setupCartWithItems($user, 2);
        $orderData = $this->getValidOrderData();

        // Act
        $response = $this->actingAsUser($user)->postJson($this->apiUrl('orders'), $orderData);

        // Assert
        $response->assertStatus(201);
        $orderId = $response->json('data.id');
        $this->assertDatabaseCount('order_items', 2);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
        ]);
    }

    /**
     * @test
     * Creating order creates status history.
     */
    public function creating_order_creates_status_history(): void
    {
        // Arrange
        $user = User::factory()->create();
        $this->setupCartWithItems($user, 2);
        $orderData = $this->getValidOrderData();

        // Act
        $response = $this->actingAsUser($user)->postJson($this->apiUrl('orders'), $orderData);

        // Assert
        $response->assertStatus(201);
        $orderId = $response->json('data.id');
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $orderId,
            'status' => 'pending',
            'notes' => 'Order created',
        ]);
    }

    /**
     * @test
     * Order total is calculated correctly.
     */
    public function order_total_is_calculated_correctly(): void
    {
        // Arrange
        $user = User::factory()->create();
        $cart = $this->setupCartWithItems($user, 2);
        $subtotal = $cart->total_amount;
        $expectedShipping = $subtotal > 100 ? 0 : 15;
        $expectedTax = $subtotal * 0.1;
        $expectedTotal = $subtotal + $expectedShipping + $expectedTax;
        
        $orderData = $this->getValidOrderData();

        // Act
        $response = $this->actingAsUser($user)->postJson($this->apiUrl('orders'), $orderData);

        // Assert
        $response->assertStatus(201);
        $this->assertEquals($subtotal, $response->json('data.subtotal'));
        $this->assertEquals($expectedShipping, $response->json('data.shipping_amount'));
        $this->assertEqualsWithDelta($expectedTax, $response->json('data.tax_amount'), 0.01);
        $this->assertEqualsWithDelta($expectedTotal, $response->json('data.total_amount'), 0.01);
    }

    /**
     * @test
     * Unauthenticated user cannot create order.
     */
    public function unauthenticated_user_cannot_create_order(): void
    {
        // Act
        $response = $this->postJson($this->apiUrl('orders'), []);

        // Assert
        $response->assertStatus(401);
    }

    // ==========================================
    // TRACKING TESTS
    // ==========================================

    /**
     * @test
     * User can retrieve order tracking information.
     */
    public function user_can_retrieve_order_tracking_information(): void
    {
        // Arrange
        $user = User::factory()->create();
        $order = Order::factory()->forUser($user)->shipped()->create([
            'tracking_number' => 'TRACK-12345678',
            'carrier' => 'FedEx',
        ]);
        
        OrderStatusHistory::factory()->create([
            'order_id' => $order->id,
            'status' => 'shipped',
            'notes' => 'Order shipped',
        ]);

        // Act
        $response = $this->actingAsUser($user)->getJson($this->apiUrl("orders/{$order->id}/tracking"));

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'order_number',
                    'status',
                    'carrier',
                    'tracking_number',
                    'events',
                ],
            ])
            ->assertJson([
                'data' => [
                    'order_number' => $order->order_number,
                    'status' => 'shipped',
                    'carrier' => 'FedEx',
                    'tracking_number' => 'TRACK-12345678',
                ],
            ]);
    }

    /**
     * @test
     * User cannot retrieve tracking for another user's order.
     */
    public function user_cannot_retrieve_tracking_for_another_users_order(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $order = Order::factory()->forUser($otherUser)->create();

        // Act
        $response = $this->actingAsUser($user)->getJson($this->apiUrl("orders/{$order->id}/tracking"));

        // Assert
        $response->assertStatus(404);
    }

    /**
     * @test
     * Retrieving tracking for non-existent order returns 404.
     */
    public function retrieving_tracking_for_non_existent_order_returns_404(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAsUser($user)->getJson($this->apiUrl('orders/99999/tracking'));

        // Assert
        $response->assertStatus(404);
    }

    /**
     * @test
     * Unauthenticated user cannot retrieve order tracking.
     */
    public function unauthenticated_user_cannot_retrieve_order_tracking(): void
    {
        // Act
        $response = $this->getJson($this->apiUrl('orders/1/tracking'));

        // Assert
        $response->assertStatus(401);
    }
}
