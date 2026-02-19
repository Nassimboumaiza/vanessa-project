<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Order Status Constants - COD Workflow
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PREPARING = 'preparing';
    public const STATUS_READY_FOR_DELIVERY = 'ready_for_delivery';
    public const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';

    /**
     * Payment Status Constants - COD Specific
     */
    public const PAYMENT_STATUS_PENDING = 'pending';
    public const PAYMENT_STATUS_PAID = 'paid';
    public const PAYMENT_STATUS_FAILED = 'failed';

    /**
     * Valid Status Transitions Matrix
     * Defines which statuses an order can transition to from current status
     */
    private const VALID_TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_PREPARING, self::STATUS_CANCELLED],
        self::STATUS_PREPARING => [self::STATUS_READY_FOR_DELIVERY, self::STATUS_CANCELLED],
        self::STATUS_READY_FOR_DELIVERY => [self::STATUS_OUT_FOR_DELIVERY, self::STATUS_CANCELLED],
        self::STATUS_OUT_FOR_DELIVERY => [self::STATUS_DELIVERED, self::STATUS_CANCELLED],
        self::STATUS_DELIVERED => [self::STATUS_REFUNDED],
        self::STATUS_CANCELLED => [],
        self::STATUS_REFUNDED => [],
    ];

    protected $fillable = [
        'order_number',
        'user_id',
        'status',
        'payment_status',
        'payment_method',
        'payment_transaction_id',
        'currency',
        'subtotal',
        'discount_amount',
        'shipping_amount',
        'tax_amount',
        'total_amount',
        'shipping_first_name',
        'shipping_last_name',
        'shipping_company',
        'shipping_address_line_1',
        'shipping_address_line_2',
        'shipping_city',
        'shipping_state',
        'shipping_postal_code',
        'shipping_country',
        'shipping_phone',
        'billing_first_name',
        'billing_last_name',
        'billing_company',
        'billing_address_line_1',
        'billing_address_line_2',
        'billing_city',
        'billing_state',
        'billing_postal_code',
        'billing_country',
        'billing_phone',
        'customer_notes',
        'admin_notes',
        'coupon_code',
        'shipped_at',
        'delivered_at',
        'paid_at',
        'tracking_number',
        'carrier',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    /**
     * Get all valid statuses for select dropdowns
     */
    public static function getAllStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_CONFIRMED => 'Confirmed',
            self::STATUS_PREPARING => 'Preparing',
            self::STATUS_READY_FOR_DELIVERY => 'Ready for Delivery',
            self::STATUS_OUT_FOR_DELIVERY => 'Out for Delivery',
            self::STATUS_DELIVERED => 'Delivered',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_REFUNDED => 'Refunded',
        ];
    }

    /**
     * Get all valid payment statuses
     */
    public static function getAllPaymentStatuses(): array
    {
        return [
            self::PAYMENT_STATUS_PENDING => 'Payment Pending',
            self::PAYMENT_STATUS_PAID => 'Paid',
            self::PAYMENT_STATUS_FAILED => 'Payment Failed',
        ];
    }

    /**
     * Check if status transition is valid
     */
    public function canTransitionTo(string $newStatus): bool
    {
        $currentStatus = $this->status;

        if (! isset(self::VALID_TRANSITIONS[$currentStatus])) {
            return false;
        }

        return in_array($newStatus, self::VALID_TRANSITIONS[$currentStatus], true);
    }

    /**
     * Transition order to new status with validation
     */
    public function transitionTo(string $newStatus, ?string $notes = null): void
    {
        if (! $this->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException(
                "Invalid status transition from '{$this->status}' to '{$newStatus}'"
            );
        }

        $oldStatus = $this->status;
        $this->update(['status' => $newStatus]);

        $this->statusHistories()->create([
            'status' => $newStatus,
            'previous_status' => $oldStatus,
            'notes' => $notes,
        ]);

        // Auto-update payment status for COD on delivery
        if ($newStatus === self::STATUS_DELIVERED && $this->payment_method === 'cash_on_delivery') {
            $this->markAsPaid();
        }
    }

    /**
     * Mark order as paid (COD: cash collected on delivery)
     */
    public function markAsPaid(?string $notes = null): void
    {
        $this->update([
            'payment_status' => self::PAYMENT_STATUS_PAID,
            'paid_at' => now(),
        ]);

        $this->statusHistories()->create([
            'status' => $this->status,
            'previous_status' => $this->status,
            'notes' => $notes ?? 'Payment received - Cash on delivery',
        ]);
    }

    /**
     * Cancel order (if allowed by current status)
     */
    public function cancel(string $reason): void
    {
        if (! $this->canTransitionTo(self::STATUS_CANCELLED)) {
            throw new \InvalidArgumentException(
                "Order cannot be cancelled from '{$this->status}' status"
            );
        }

        $this->transitionTo(self::STATUS_CANCELLED, "Cancelled: {$reason}");
    }

    /**
     * Scope: Get orders by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Get orders by payment status
     */
    public function scopeByPaymentStatus($query, string $paymentStatus)
    {
        return $query->where('payment_status', $paymentStatus);
    }

    /**
     * Scope: Active orders (not cancelled or refunded)
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [self::STATUS_CANCELLED, self::STATUS_REFUNDED]);
    }

    /**
     * Scope: Pending payment (COD orders awaiting delivery)
     */
    public function scopePendingPayment($query)
    {
        return $query->where('payment_status', self::PAYMENT_STATUS_PENDING)
            ->where('payment_method', 'cash_on_delivery');
    }

    /**
     * Accessor: Check if order is cancellable
     */
    public function getIsCancellableAttribute(): bool
    {
        return $this->canTransitionTo(self::STATUS_CANCELLED);
    }

    /**
     * Accessor: Check if order is paid
     */
    public function getIsPaidAttribute(): bool
    {
        return $this->payment_status === self::PAYMENT_STATUS_PAID;
    }

    /**
     * Accessor: Human-readable status label
     */
    public function getStatusLabelAttribute(): string
    {
        return self::getAllStatuses()[$this->status] ?? $this->status;
    }

    /**
     * Accessor: Human-readable payment status label
     */
    public function getPaymentStatusLabelAttribute(): string
    {
        return self::getAllPaymentStatuses()[$this->payment_status] ?? $this->payment_status;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
