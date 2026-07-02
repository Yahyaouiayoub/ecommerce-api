<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefundItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'refund_id',
        'order_item_id',
        'quantity',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    // =========================
    // RELATIONSHIPS
    // =========================
    public function refund()
    {
        return $this->belongsTo(Refund::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }
}
