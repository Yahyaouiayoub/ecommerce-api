<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'invoice_id',
        'amount',
        'currency',
        'payment_method',
        'payment_type',
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

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    // =========================
    // ACCESSORS
    // =========================
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending'  => 'Pending',
            'paid'     => 'Paid',
            'failed'   => 'Failed',
            'refunded' => 'Refunded',
            default    => ucfirst($this->status),
        };
    }

    public function getAmountFormattedAttribute(): string
    {
        return number_format($this->amount, 2) . ' ' . $this->currency;
    }

    public function getPaymentTypeLabelAttribute(): string
    {
        return match ($this->payment_type) {
            'full'       => 'Full Payment (100%)',
            'partial_50' => 'Partial Payment (50%)',
            'partial_30' => 'Partial Payment (30%)',
            'custom'     => 'Custom Payment',
            default      => ucfirst($this->payment_type),
        };
    }

    // =========================
    // HELPERS
    // =========================
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
