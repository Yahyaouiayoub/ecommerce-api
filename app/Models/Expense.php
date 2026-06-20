<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'amount',
        'category',
        'note',
        'description',
        'expense_date',
        'created_by',
    ];

    protected $casts = [
        'expense_date' => 'date',
    ];

    // =========================
    // RELATIONSHIPS
    // =========================
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
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
