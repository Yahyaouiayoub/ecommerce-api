<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'price',
        'stock',
        'sku',
        'color',
        'size',
        'storage',
        'attributes',
        'is_default',
        'sort_order',
    ];

    protected $casts = [
        'attributes' => 'array',
        'is_default' => 'boolean',
        'price' => 'decimal:2',
    ];

    // =========================
    // RELATIONSHIPS
    // =========================
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // =========================
    // ACCESSORS
    // =========================
    public function getEffectivePriceAttribute(): float
    {
        return (float) ($this->price ?? $this->product->getEffectivePrice());
    }

    public function getPriceFormattedAttribute(): string
    {
        return number_format($this->effective_price, 2) . ' MAD';
    }

    public function getInStockAttribute(): bool
    {
        return $this->stock > 0;
    }

    public function getStockStatusAttribute(): string
    {
        if ($this->stock > 5) return 'In Stock';
        if ($this->stock > 0) return 'Low Stock';
        return 'Out of Stock';
    }

    // =========================
    // HELPERS
    // =========================
    public function isInStock(): bool
    {
        return $this->stock > 0;
    }

    /**
     * Get attribute groups for display (e.g., Color, Size, Storage).
     */
    public static function getAttributeGroups(int $productId): array
    {
        $variants = static::where('product_id', $productId)
            ->orderBy('sort_order')
            ->get();

        $groups = [];

        foreach ($variants as $variant) {
            // Collect unique colors
            if ($variant->color) {
                $groups['color']['label'] = 'Color';
                $groups['color']['options'][$variant->color] = [
                    'value' => $variant->color,
                    'variant_id' => $variant->id,
                ];
            }
            // Collect unique sizes
            if ($variant->size) {
                $groups['size']['label'] = 'Size';
                $groups['size']['options'][$variant->size] = [
                    'value' => $variant->size,
                    'variant_id' => $variant->id,
                ];
            }
            // Collect unique storage
            if ($variant->storage) {
                $groups['storage']['label'] = 'Storage';
                $groups['storage']['options'][$variant->storage] = [
                    'value' => $variant->storage,
                    'variant_id' => $variant->id,
                ];
            }
            // Collect custom attributes
            if ($variant->attributes) {
                foreach ($variant->attributes as $key => $value) {
                    $groups[$key]['label'] = ucfirst($key);
                    $groups[$key]['options'][$value] = [
                        'value' => $value,
                        'variant_id' => $variant->id,
                    ];
                }
            }
        }

        return $groups;
    }
}
