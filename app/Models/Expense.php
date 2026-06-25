<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'product_id',
        'amount',
        'total_cost',
        'quantity',
        'category',
        'note',
        'description',
        'expense_date',
        'created_by',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount'       => 'decimal:2',
        'total_cost'   => 'decimal:2',
    ];

    // =========================
    // RELATIONSHIPS
    // =========================
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // =========================
    // ACCESSORS
    // =========================
    public function getAmountFormattedAttribute(): string
    {
        return number_format($this->amount, 2) . ' MAD';
    }

    public function getCategoryLabelAttribute(): string
    {
        return $this->category ? ucfirst($this->category) : 'Uncategorized';
    }
}
