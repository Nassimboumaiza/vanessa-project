<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'short_description',
        'category_id',
        'price',
        'compare_price',
        'cost_price',
        'stock_quantity',
        'sku',
        'barcode',
        'weight',
        'dimensions',
        'notes',
        'concentration',
        'volume_ml',
        'country_of_origin',
        'brand',
        'perfumer',
        'release_year',
        'gender',
        'is_active',
        'is_featured',
        'is_new',
        'rating_average',
        'rating_count',
        'meta_title',
        'meta_description',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'weight' => 'decimal:2',
        'rating_average' => 'decimal:1',
        'dimensions' => 'json',
        'notes' => 'json',
        'meta_title' => 'json',
        'meta_description' => 'json',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'is_new' => 'boolean',
        'release_year' => 'integer',
        'volume_ml' => 'integer',
        'stock_quantity' => 'integer',
        'rating_count' => 'integer',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $product): void {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        });

        static::updating(function (self $product): void {
            if ($product->isDirty('name')) {
                $product->slug = Str::slug($product->name);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Configure audit logging options for products.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'price',
                'compare_price',
                'stock_quantity',
                'is_active',
                'is_featured',
                'is_new',
                'category_id',
            ])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName): string => "Product {$eventName}")
            ->useLogName(config('activitylog.log_names.products', 'products'));
    }
}
