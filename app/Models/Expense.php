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
        'expense_date',
    ];

    protected $casts = [
        'expense_date' => 'date',
    ];

    // =========================
    // HELPERS
    // =========================
    public function getAmountFormattedAttribute()
    {
        return number_format($this->amount, 2) . ' MAD';
    }
}
