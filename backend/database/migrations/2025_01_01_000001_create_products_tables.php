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
        // Categories Table
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->string('image', 255)->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->onDelete('set null');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('slug');
            $table->index('is_active');
        });

        // Products Table
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 200);
            $table->string('slug', 200)->unique();
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->decimal('price', 10, 2);
            $table->decimal('compare_price', 10, 2)->nullable();
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->string('sku', 100)->unique();
            $table->string('barcode', 100)->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->json('dimensions')->nullable(); // length, width, height
            $table->json('notes')->nullable(); // fragrance notes: top, middle, base
            $table->string('concentration', 50)->nullable(); // Eau de Parfum, Eau de Toilette, etc.
            $table->integer('volume_ml')->nullable(); // 50ml, 100ml, etc.
            $table->string('country_of_origin', 100)->nullable();
            $table->string('brand', 100)->nullable();
            $table->string('perfumer', 100)->nullable();
            $table->year('release_year')->nullable();
            $table->string('gender', 20)->nullable(); // unisex, masculine, feminine
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_new')->default(false);
            $table->decimal('rating_average', 2, 1)->default(0);
            $table->integer('rating_count')->default(0);
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('slug');
            $table->index('category_id');
            $table->index('is_active');
            $table->index('is_featured');
            $table->index('is_new');
            $table->index('price');
            $table->index('created_at');
        });

        // Product Images Table
        Schema::create('product_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('image_path', 255);
            $table->string('alt_text', 255)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index('product_id');
        });

        // Product Variants Table (for different sizes)
        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('name', 100); // e.g., "50ml", "100ml"
            $table->string('sku', 100)->unique();
            $table->decimal('price', 10, 2);
            $table->decimal('compare_price', 10, 2)->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->integer('volume_ml')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('product_id');
            $table->index('sku');
        });

        // Product Inventory Log
        Schema::create('inventory_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->onDelete('cascade');
            $table->enum('type', ['in', 'out', 'adjustment']);
            $table->integer('quantity');
            $table->integer('previous_quantity');
            $table->integer('new_quantity');
            $table->string('reference_type', 50)->nullable(); // order, adjustment, etc.
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps();

            $table->index(['product_id', 'variant_id']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_logs');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
