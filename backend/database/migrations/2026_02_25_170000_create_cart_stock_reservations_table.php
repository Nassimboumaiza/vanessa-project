<?php

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
        Schema::create('cart_stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->onDelete('cascade');
            $table->integer('quantity_reserved')->unsigned();
            $table->timestamp('expires_at');
            $table->string('reservation_token', 64)->unique(); // For tracking/cleanup
            $table->enum('status', ['active', 'converted', 'expired', 'released'])->default('active');
            $table->timestamps();

            // Indexes for efficient queries
            $table->index(['user_id', 'status']);
            $table->index(['product_id', 'status']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_stock_reservations');
    }
};
