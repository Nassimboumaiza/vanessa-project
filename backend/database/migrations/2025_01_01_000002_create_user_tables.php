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
        // Users extension
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('avatar', 255)->nullable()->after('phone');
            $table->enum('gender', ['male', 'female', 'other'])->nullable()->after('avatar');
            $table->date('birthdate')->nullable()->after('gender');
            $table->enum('role', ['customer', 'admin', 'manager'])->default('customer')->after('birthdate');
            $table->boolean('is_active')->default(true)->after('role');
            $table->timestamp('last_login_at')->nullable()->after('email_verified_at');
            $table->string('preferred_language', 10)->default('en')->after('last_login_at');
            $table->string('preferred_currency', 3)->default('USD')->after('preferred_language');
            $table->softDeletes();
        });

        // User Addresses
        Schema::create('user_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('label', 50)->nullable(); // Home, Work, etc.
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('company', 100)->nullable();
            $table->string('address_line_1', 255);
            $table->string('address_line_2', 255)->nullable();
            $table->string('city', 100);
            $table->string('state', 100);
            $table->string('postal_code', 20);
            $table->string('country', 100);
            $table->string('phone', 20)->nullable();
            $table->boolean('is_default')->default(false);
            $table->enum('type', ['shipping', 'billing'])->default('shipping');
            $table->timestamps();

            $table->index('user_id');
        });

        // Wishlists
        Schema::create('wishlists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['user_id', 'product_id', 'variant_id']);
            $table->index('user_id');
        });

        // Shopping Carts
        Schema::create('carts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('session_id', 100)->nullable();
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->integer('total_items')->default(0);
            $table->timestamps();

            $table->index('user_id');
            $table->index('session_id');
        });

        // Cart Items
        Schema::create('cart_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cart_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->onDelete('cascade');
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->timestamps();

            $table->index('cart_id');
            $table->unique(['cart_id', 'product_id', 'variant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('wishlists');
        Schema::dropIfExists('user_addresses');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'phone', 'avatar', 'gender', 'birthdate', 'role', 'is_active',
                'last_login_at', 'preferred_language',
                'preferred_currency', 'deleted_at',
            ]);
        });
    }
};
