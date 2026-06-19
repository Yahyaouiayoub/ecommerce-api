<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'amount',
        'currency',
        'payment_method',
        'transaction_id',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    // =========================
    // RELATIONSHIPS
    // =========================
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // =========================
    // HELPERS
    // =========================
    public function getStatusLabelAttribute()
    {
        return ucfirst($this->status);
    }

    public function getAmountFormattedAttribute()
    {
        return number_format($this->amount, 2) . ' ' . $this->currency;
    }

    public function isPaid()
    {
        return $this->status === 'paid';
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }
}
