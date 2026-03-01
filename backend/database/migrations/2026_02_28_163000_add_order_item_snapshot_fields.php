<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add product snapshot fields to order_items table.
 * 
 * This ensures orders remain historically accurate even if:
 * - Product price changes
 * - Product is deleted
 * - Product name is updated
 * - Variant attributes change
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            // Product snapshot fields (immutable after order creation)
            $table->string('product_slug', 255)->nullable()->after('product_name');
            $table->string('product_image', 500)->nullable()->after('product_sku');
            $table->decimal('compare_price', 10, 2)->nullable()->after('unit_price');
            $table->decimal('tax_rate', 5, 4)->default(0.0000)->after('tax_amount');
            
            // Variant snapshot as JSON (size, concentration, volume, etc.)
            $table->json('variant_data')->nullable()->after('variant_name');
            
            // Future-safe: Currency per item (for multi-currency support)
            $table->string('currency', 3)->default('USD')->after('total_price');
            
            // Refund tracking
            $table->decimal('refunded_amount', 10, 2)->default(0)->after('currency');
            $table->integer('refunded_quantity')->default(0)->after('refunded_amount');
            
            // Index for product lookups (even if product is deleted)
            $table->index('product_slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn([
                'product_slug',
                'product_image',
                'compare_price',
                'tax_rate',
                'variant_data',
                'currency',
                'refunded_amount',
                'refunded_quantity',
            ]);
            
            $table->dropIndex(['product_slug']);
        });
    }
};
