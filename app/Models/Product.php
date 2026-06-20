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
        'slug',
        'description',
        'price',
        'stock',
        'sku',
        'thumbnail',
        'video_url',
        'is_active',
        'featured',
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

    // =========================
    // HELPERS
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

}
