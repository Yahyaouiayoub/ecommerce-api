<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Revenue extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'amount',
        'source',
        'reference',
        'note',
        'revenue_date',
    ];

    protected $casts = [
        'revenue_date' => 'datetime',
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
    public function getAmountFormattedAttribute()
    {
        return number_format($this->amount, 2) . ' MAD';
    }
}
