<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'price',
    ];

    // =========================
    // RELATIONSHIPS
    // =========================
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // =========================
    // HELPERS
    // =========================
    public function getSubtotalAttribute()
    {
        return $this->quantity * $this->price;
    }

    public function getSubtotalFormattedAttribute()
    {
        return number_format($this->subtotal, 2) . ' MAD';
    }
}
