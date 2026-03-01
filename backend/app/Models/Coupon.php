<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Carbon\Carbon;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Coupon extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'code',
        'type',
        'value',
        'usage_limit',
        'used_count',
        'start_date',
        'end_date',
        'min_order_amount',
        'is_active',
        'description',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => 'boolean',
        'usage_limit' => 'integer',
        'used_count' => 'integer',
    ];

    // Coupon types
    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_FIXED = 'fixed';

    /**
     * Users who have redeemed this coupon
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'coupon_user')
            ->withPivot(['redeemed_at', 'order_id'])
            ->withTimestamps();
    }

    /**
     * Scope: Active coupons
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Valid coupons (active and within date range)
     */
    public function scopeValid($query)
    {
        $now = Carbon::now();

        return $query->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('start_date')
                    ->orWhere('start_date', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $now);
            });
    }

    /**
     * Scope: Expired coupons
     */
    public function scopeExpired($query)
    {
        return $query->where('end_date', '<', Carbon::now());
    }

    /**
     * Scope: By code
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }

    /**
     * Check if coupon is valid for use
     */
    public function isValid(): bool
    {
        return $this->is_active
            && $this->isWithinDateRange()
            && $this->hasRemainingUsage();
    }

    /**
     * Check if coupon is within valid date range
     */
    public function isWithinDateRange(): bool
    {
        $now = Carbon::now();

        if ($this->start_date && $now->lt($this->start_date)) {
            return false;
        }

        if ($this->end_date && $now->gt($this->end_date)) {
            return false;
        }

        return true;
    }

    /**
     * Check if coupon has remaining usage
     */
    public function hasRemainingUsage(): bool
    {
        if ($this->usage_limit === null) {
            return true;
        }

        return $this->used_count < $this->usage_limit;
    }

    /**
     * Check if order amount meets minimum requirement
     */
    public function meetsMinimumOrderAmount(float $orderAmount): bool
    {
        return $orderAmount >= $this->min_order_amount;
    }

    /**
     * Calculate discount amount for given subtotal
     */
    public function calculateDiscount(float $subtotal): float
    {
        if ($this->type === self::TYPE_PERCENTAGE) {
            return round($subtotal * ($this->value / 100), 2);
        }

        return min($this->value, $subtotal);
    }

    /**
     * Increment usage count atomically
     */
    public function incrementUsage(): bool
    {
        if (!$this->hasRemainingUsage()) {
            return false;
        }

        // Atomic increment with check to prevent over-redemption
        $updated = static::where('id', $this->id)
            ->where(function ($q) {
                $q->whereNull('usage_limit')
                    ->orWhereRaw('used_count < usage_limit');
            })
            ->increment('used_count');

        if ($updated) {
            $this->refresh();
        }

        return $updated > 0;
    }

    /**
     * Decrement usage count (for order cancellation)
     */
    public function decrementUsage(): void
    {
        static::where('id', $this->id)
            ->where('used_count', '>', 0)
            ->decrement('used_count');

        $this->refresh();
    }

    /**
     * Get remaining usage count
     */
    public function getRemainingUsage(): ?int
    {
        if ($this->usage_limit === null) {
            return null;
        }

        return max(0, $this->usage_limit - $this->used_count);
    }

    /**
     * Get formatted value for display
     */
    public function getFormattedValueAttribute(): string
    {
        if ($this->type === self::TYPE_PERCENTAGE) {
            // Show without decimals for whole numbers
            return ($this->value == floor($this->value) ? (int) $this->value : $this->value) . '%';
        }

        return '$' . number_format($this->value, 2);
    }

    /**
     * Configure audit logging options for coupons.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'code',
                'type',
                'value',
                'usage_limit',
                'used_count',
                'is_active',
                'start_date',
                'end_date',
                'min_order_amount',
            ])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName): string => "Coupon {$eventName}")
            ->useLogName(config('activitylog.log_names.coupons', 'coupons'));
    }
}
