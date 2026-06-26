<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'brand_id',
        'name',
        'name_en',
        'name_fr',
        'name_ar',
        'name_es',
        'slug',
        'description',
        'description_en',
        'description_fr',
        'description_ar',
        'description_es',
        'price',
        'purchase_price',
        'margin_percentage',
        'final_price',
        'discount_price',
        'stock',
        'sku',
        'thumbnail',
        'video_url',
        'is_active',
        'featured',
    ];

    protected $casts = [
        'purchase_price'   => 'decimal:2',
        'margin_percentage' => 'decimal:2',
        'final_price'      => 'decimal:2',
        'discount_price'   => 'decimal:2',
    ];

    // =========================
    // RELATIONSHIPS
    // =========================
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function cart()
    {
        return $this->hasMany(Cart::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    public function defaultVariant()
    {
        return $this->hasOne(ProductVariant::class)->where('is_default', true);
    }

    // =========================
    // ACCESSORS
    // =========================
    public function getPriceFormattedAttribute()
    {
        return number_format($this->price, 2) . ' MAD';
    }

    public function getStockStatusAttribute()
    {
        if ($this->stock > 5) {
            return 'In Stock';
        } elseif ($this->stock > 0) {
            return 'Low Stock';
        } else {
            return 'Out of Stock';
        }
    }

    /**
     * Auto-calculate final_price = purchase_price + (purchase_price * margin_percentage / 100).
     */
    public static function calculateFinalPrice(float $purchasePrice, float $marginPercentage): float
    {
        return round($purchasePrice + ($purchasePrice * $marginPercentage / 100), 2);
    }

    /**
     * Get the effective selling price (discount_price if set and valid, otherwise price).
     */
    public function getEffectivePrice(): float
    {
        if ($this->discount_price !== null && $this->discount_price > 0) {
            return (float) $this->discount_price;
        }
        return (float) $this->price;
    }

    /**
     * Check if margin is positive (final_price > purchase_price).
     */
    public function hasPositiveMargin(): bool
    {
        return (float) $this->final_price > (float) $this->purchase_price;
    }

    /**
     * Get the translated name for a given locale. Falls back to default name.
     */
    public function getNameForLocale(string $locale): ?string
    {
        $field = "name_{$locale}";
        return $this->$field ?? $this->name;
    }

    /**
     * Get the translated description for a given locale. Falls back to default description.
     */
    public function getDescriptionForLocale(string $locale): ?string
    {
        $field = "description_{$locale}";
        return $this->$field ?? $this->description;
    }

}
