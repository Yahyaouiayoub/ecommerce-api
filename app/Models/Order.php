<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_number',
        'total_price',
        'status',
        'payment_method',
        'shipping_address',
        'notes',
    ];

    protected $casts = [
        'shipping_address' => 'array',
    ];

    // =========================
    // RELATIONSHIPS
    // =========================
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function revenue()
    {
        return $this->hasOne(Revenue::class);
    }

    // =========================
    // HELPERS
    // =========================
    public function getStatusLabelAttribute()
    {
        return ucfirst($this->status);
    }

    public function getTotalFormattedAttribute()
    {
        return number_format($this->total_price, 2) . ' MAD';
    }
}
