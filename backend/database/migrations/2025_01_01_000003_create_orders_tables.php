<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Orders
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number', 50)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('status', ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'])->default('pending');
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->string('payment_method', 50)->nullable();
            $table->string('payment_transaction_id', 100)->nullable();
            $table->string('currency', 3)->default('USD');

            // Pricing
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('shipping_amount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);

            // Shipping Address
            $table->string('shipping_first_name', 100);
            $table->string('shipping_last_name', 100);
            $table->string('shipping_company', 100)->nullable();
            $table->string('shipping_address_line_1', 255);
            $table->string('shipping_address_line_2', 255)->nullable();
            $table->string('shipping_city', 100);
            $table->string('shipping_state', 100);
            $table->string('shipping_postal_code', 20);
            $table->string('shipping_country', 100);
            $table->string('shipping_phone', 20)->nullable();

            // Billing Address
            $table->string('billing_first_name', 100);
            $table->string('billing_last_name', 100);
            $table->string('billing_company', 100)->nullable();
            $table->string('billing_address_line_1', 255);
            $table->string('billing_address_line_2', 255)->nullable();
            $table->string('billing_city', 100);
            $table->string('billing_state', 100);
            $table->string('billing_postal_code', 20);
            $table->string('billing_country', 100);
            $table->string('billing_phone', 20)->nullable();

            // Additional
            $table->text('customer_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->string('coupon_code', 50)->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->string('carrier', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('order_number');
            $table->index('user_id');
            $table->index('status');
            $table->index('payment_status');
            $table->index('created_at');
        });

        // Order Items
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->onDelete('set null');
            $table->string('product_name', 200);
            $table->string('product_sku', 100);
            $table->string('variant_name', 100)->nullable();
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total_price', 10, 2);
            $table->timestamps();

            $table->index('order_id');
        });

        // Order Status History
        Schema::create('order_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded']);
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps();

            $table->index('order_id');
        });

        // Reviews
        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('set null');
            $table->tinyInteger('rating'); // 1-5
            $table->string('title', 200)->nullable();
            $table->text('content');
            $table->json('pros')->nullable();
            $table->json('cons')->nullable();
            $table->boolean('is_verified_purchase')->default(false);
            $table->boolean('is_approved')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->integer('helpful_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'is_approved']);
            $table->index('user_id');
            $table->unique(['user_id', 'product_id', 'order_id']);
        });

        // Review Images
        Schema::create('review_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('review_id')->constrained()->onDelete('cascade');
            $table->string('image_path', 255);
            $table->timestamps();

            $table->index('review_id');
        });

        // Newsletter Subscribers
        Schema::create('newsletter_subscribers', function (Blueprint $table): void {
            $table->id();
            $table->string('email', 255)->unique();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->enum('status', ['subscribed', 'unsubscribed', 'bounced'])->default('subscribed');
            $table->timestamp('subscribed_at');
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_images');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('newsletter_subscribers');
    }
};
