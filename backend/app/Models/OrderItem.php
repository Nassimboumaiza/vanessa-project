<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    /** @use HasFactory<\Database\Factories\OrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'variant_id',
        // Product snapshot fields (immutable - preserve historical accuracy)
        'product_name',
        'product_slug',
        'product_sku',
        'product_image',
        'variant_name',
        'variant_data',
        // Pricing snapshot (at time of purchase)
        'quantity',
        'unit_price',
        'compare_price',
        'discount_amount',
        'tax_amount',
        'tax_rate',
        'total_price',
        'currency',
        // Refund tracking
        'refunded_amount',
        'refunded_quantity',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'compare_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'total_price' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'quantity' => 'integer',
        'refunded_quantity' => 'integer',
        'variant_data' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * Check if the original product still exists.
     */
    public function productExists(): bool
    {
        return $this->product()->exists();
    }

    /**
     * Get the product image URL (from snapshot or relationship fallback).
     */
    public function getImageUrlAttribute(): ?string
    {
        // Primary: use snapshot image
        if ($this->product_image) {
            return $this->product_image;
        }

        // Fallback: try to get from product relationship (if product still exists)
        if ($this->product && $this->product->images->first()) {
            return $this->product->images->first()->url;
        }

        return null;
    }

    /**
     * Get the effective product name (snapshot is primary source).
     */
    public function getEffectiveProductNameAttribute(): string
    {
        return $this->product_name ?? ($this->product?->name ?? 'Unknown Product');
    }

    /**
     * Get the effective unit price (snapshot is primary source).
     */
    public function getEffectiveUnitPriceAttribute(): string
    {
        return $this->unit_price;
    }

    /**
     * Calculate discount percentage if compare price exists.
     */
    public function getDiscountPercentageAttribute(): ?float
    {
        if (!$this->compare_price || $this->compare_price <= $this->unit_price) {
            return null;
        }

        return round((($this->compare_price - $this->unit_price) / $this->compare_price) * 100, 1);
    }

    /**
     * Check if item has been partially or fully refunded.
     */
    public function isRefunded(): bool
    {
        return $this->refunded_quantity > 0 || $this->refunded_amount > 0;
    }

    /**
     * Get remaining refundable quantity.
     */
    public function getRefundableQuantityAttribute(): int
    {
        return max(0, $this->quantity - $this->refunded_quantity);
    }

    /**
     * Get remaining refundable amount.
     */
    public function getRefundableAmountAttribute(): float
    {
        return max(0, $this->total_price - $this->refunded_amount);
    }
}
