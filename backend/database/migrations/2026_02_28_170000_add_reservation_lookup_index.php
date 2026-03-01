<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds composite index for efficient stock reservation lookups with locking.
     */
    public function up(): void
    {
        Schema::table('cart_stock_reservations', function (Blueprint $table) {
            // Composite index for reserveItem() queries that lock and sum active reservations
            // Covers: forProduct($productId, $variantId)->active()->lockForUpdate()
            $table->index(['product_id', 'product_variant_id', 'status', 'expires_at'], 'reservation_lookup_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_stock_reservations', function (Blueprint $table) {
            $table->dropIndex('reservation_lookup_index');
        });
    }
};
