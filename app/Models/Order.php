<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'order_number',
        'total_price',
        'status',
        'payment_method',
        'address_id',
        'shipping_method_id',
        'notes',
        'guest_email',
        'guest_name',
    ];

    protected $casts = [];

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

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function revenue()
    {
        return $this->hasOne(Revenue::class);
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function shippingMethod()
    {
        return $this->belongsTo(ShippingMethod::class);
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
