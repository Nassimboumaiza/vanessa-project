<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartStockReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'product_variant_id',
        'quantity_reserved',
        'expires_at',
        'reservation_token',
        'status',
    ];

    protected $casts = [
        'quantity_reserved' => 'integer',
        'expires_at' => 'datetime',
    ];

    /**
     * Reservation status constants
     */
    const STATUS_ACTIVE = 'active';
    const STATUS_CONVERTED = 'converted';
    const STATUS_EXPIRED = 'expired';
    const STATUS_RELEASED = 'released';

    /**
     * Default reservation duration in minutes
     */
    const DEFAULT_RESERVATION_MINUTES = 30;

    /**
     * User relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Product relationship
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Product variant relationship
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * Check if reservation is still active and not expired
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->expires_at->isFuture();
    }

    /**
     * Check if reservation has expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast() || $this->status === self::STATUS_EXPIRED;
    }

    /**
     * Scope for active reservations
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
                     ->where('expires_at', '>', now());
    }

    /**
     * Scope for expired reservations
     */
    public function scopeExpired($query)
    {
        return $query->where(function ($q) {
            $q->where('status', self::STATUS_EXPIRED)
              ->orWhere('expires_at', '<=', now());
        });
    }

    /**
     * Scope for user's active reservations
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for product reservations
     */
    public function scopeForProduct($query, int $productId, ?int $variantId = null)
    {
        $q = $query->where('product_id', $productId);
        if ($variantId !== null) {
            $q->where('product_variant_id', $variantId);
        }
        return $q;
    }

    /**
     * Release the reservation (make stock available again)
     */
    public function release(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        return $this->update(['status' => self::STATUS_RELEASED]);
    }

    /**
     * Mark reservation as converted to order
     */
    public function convert(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        return $this->update(['status' => self::STATUS_CONVERTED]);
    }

    /**
     * Extend reservation expiration
     */
    public function extend(int $minutes = self::DEFAULT_RESERVATION_MINUTES): bool
    {
        return $this->update([
            'expires_at' => now()->addMinutes($minutes),
        ]);
    }

    /**
     * Generate unique reservation token
     */
    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
